#!/usr/bin/env node
import { promises as fs } from "node:fs";
import path from "node:path";

import {
  ALLOWED_BINARY_EXTENSIONS,
  REPO_ROOT,
  compileSecretPatterns,
  extractAddedLines,
  getBypassEnvName,
  getStagedFiles,
  isHookBypassed,
  isLikelyBinaryFile,
  loadPolicy,
  matchesBlockedPath,
  runCommand,
} from "./lib/hook-utils.mjs";

const policy = await loadPolicy();

if (isHookBypassed(policy)) {
  console.log(`[hooks] ${getBypassEnvName(policy)} is set, skipping pre-commit checks.`);
  process.exit(0);
}

const stagedFiles = await getStagedFiles();
if (stagedFiles.length === 0) {
  process.exit(0);
}

const failures = [];
const secretPatterns = compileSecretPatterns(policy);

for (const relativePath of stagedFiles) {
  if (matchesBlockedPath(relativePath, policy.blocked_paths || [])) {
    failures.push(
      `Blocked path: ${relativePath}. Remove it from the commit or use ${getBypassEnvName(policy)}=1.`,
    );
    continue;
  }

  const absolutePath = path.resolve(REPO_ROOT, relativePath);
  const stat = await fs.stat(absolutePath).catch(() => null);
  if (!stat || !stat.isFile()) {
    continue;
  }

  if (stat.size > policy.max_file_size_bytes) {
    failures.push(
      `File too large (${stat.size} bytes): ${relativePath}. Max allowed is ${policy.max_file_size_bytes} bytes.`,
    );
  }

  const likelyBinary = await isLikelyBinaryFile(absolutePath);
  if (likelyBinary) {
    const extension = path.extname(relativePath).toLowerCase();
    if (!ALLOWED_BINARY_EXTENSIONS.has(extension)) {
      failures.push(
        `Binary file blocked: ${relativePath}. Commit text/source files only or use ${getBypassEnvName(policy)}=1.`,
      );
    }
  }
}

for (const relativePath of stagedFiles) {
  const diffResult = await runCommand("git", [
    "diff",
    "--cached",
    "--no-color",
    "--unified=0",
    "--",
    relativePath,
  ]);

  if (diffResult.code !== 0) {
    continue;
  }

  const addedLines = extractAddedLines(diffResult.stdout);
  for (const line of addedLines) {
    for (const pattern of secretPatterns) {
      if (pattern.regex.test(line)) {
        failures.push(
          `Potential secret (${pattern.name}) detected in ${relativePath}. Remove/redact before commit.`,
        );
        break;
      }
    }
  }
}

if (failures.length > 0) {
  console.error("\n[hooks] pre-commit blocked the commit:\n");
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  console.error(
    `\nUse git commit --no-verify or ${getBypassEnvName(policy)}=1 only when you intentionally accept the risk.\n`,
  );
  process.exit(1);
}

const hasFrontendChanges = stagedFiles.some((relativePath) =>
  relativePath.startsWith("frontend/"),
);

if (hasFrontendChanges) {
  console.log("[hooks] Running lint-staged for frontend staged files...");
  const lintStagedResult = await runCommand(
    "npx",
    ["lint-staged", "--concurrent", "false"],
    { captureOutput: false },
  );

  if (lintStagedResult.code !== 0) {
    process.exit(lintStagedResult.code || 1);
  }
}

console.log("[hooks] pre-commit checks passed.");

