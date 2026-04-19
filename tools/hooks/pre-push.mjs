#!/usr/bin/env node

import {
  formatTargetCommand,
  getBypassEnvName,
  isHookBypassed,
  loadPolicy,
  resolveTargetCommand,
  runCommand,
} from "./lib/hook-utils.mjs";

const policy = await loadPolicy();
const dryRun = process.argv.includes("--dry-run");

if (isHookBypassed(policy)) {
  console.log(`[hooks] ${getBypassEnvName(policy)} is set, skipping pre-push verification.`);
  process.exit(0);
}

const changedFiles = await detectChangedFiles();

let targetsToRun = [];
if (changedFiles === null) {
  targetsToRun = ["backend_tests", "frontend_typecheck", "frontend_unit_tests"];
  console.log("[hooks] Could not resolve diff base; running full verification baseline.");
} else {
  const codeChanges = changedFiles.filter((file) => !isNonCodePath(file));
  const hasBackendChanges = codeChanges.some((file) => file.startsWith("backend/"));
  const hasFrontendChanges = codeChanges.some((file) => file.startsWith("frontend/"));
  const hasComposeChanges = codeChanges.some(isComposeRelatedPath);

  if (hasBackendChanges) {
    targetsToRun.push("backend_tests");
  }

  if (hasFrontendChanges) {
    targetsToRun.push("frontend_typecheck", "frontend_unit_tests");
  }

  if (hasComposeChanges) {
    targetsToRun.push("docker_compose_config");
  }
}

targetsToRun = Array.from(new Set(targetsToRun));

if (targetsToRun.length === 0) {
  console.log("[hooks] No backend/frontend/compose changes detected; skipping pre-push verification.");
  process.exit(0);
}

for (const targetName of targetsToRun) {
  const target = resolveTargetCommand(policy, targetName);
  console.log(`\n[hooks] Running ${targetName}: ${formatTargetCommand(target)}`);

  if (dryRun) {
    continue;
  }

  const result = await runCommand(target.command, target.args, {
    cwd: target.cwd,
    captureOutput: false,
    timeoutMs: target.timeoutMs,
  });

  if (result.error?.code === "ENOENT" && target.optional) {
    console.log(`[hooks] Skipping optional target '${targetName}' because command was not found.`);
    continue;
  }

  if (result.timedOut) {
    console.error(`[hooks] '${targetName}' timed out after ${target.timeoutMs}ms.`);
    process.exit(1);
  }

  if (result.code !== 0) {
    console.error(`[hooks] '${targetName}' failed with exit code ${result.code}.`);
    process.exit(result.code || 1);
  }
}

if (dryRun) {
  console.log("\n[hooks] Dry run completed.");
  process.exit(0);
}

console.log("\n[hooks] pre-push verification passed.");

async function detectChangedFiles() {
  const upstream = await resolveDiffBase();
  if (!upstream) {
    return null;
  }

  const diffResult = await runCommand("git", ["diff", "--name-only", `${upstream}...HEAD`]);
  if (diffResult.code !== 0) {
    return null;
  }

  return diffResult.stdout
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => line.replace(/\\/g, "/"));
}

async function resolveDiffBase() {
  const upstreamResult = await runCommand("git", [
    "rev-parse",
    "--abbrev-ref",
    "--symbolic-full-name",
    "@{u}",
  ]);
  if (upstreamResult.code === 0) {
    const upstream = upstreamResult.stdout.trim();
    if (upstream) {
      return upstream;
    }
  }

  const branchResult = await runCommand("git", ["rev-parse", "--abbrev-ref", "HEAD"]);
  const branch = branchResult.code === 0 ? branchResult.stdout.trim() : "";

  const candidates = [];
  if (branch && branch !== "HEAD") {
    candidates.push(`origin/${branch}`);
  }
  candidates.push("origin/dev", "origin/main");

  for (const candidate of candidates) {
    const exists = await runCommand("git", [
      "show-ref",
      "--verify",
      "--quiet",
      `refs/remotes/${candidate}`,
    ]);
    if (exists.code === 0) {
      return candidate;
    }
  }

  return null;
}

function isComposeRelatedPath(filePath) {
  const normalized = filePath.replace(/\\/g, "/");
  return (
    normalized === "docker-compose.yml" ||
    normalized === "compose.yml" ||
    normalized === "compose.yaml" ||
    /^docker-compose\..*\.ya?ml$/i.test(normalized) ||
    normalized.startsWith("backend/docker/") ||
    normalized === "redis.conf"
  );
}

function isNonCodePath(filePath) {
  const normalized = filePath.replace(/\\/g, "/");
  // Docs-equivalent paths that cannot affect runtime behavior and must not
  // trigger the full test suite:
  //   *.md                       (READMEs, changelogs, in-repo docs)
  //   .env.example               (env template)
  //   .env.<suffix>.example      (env templates like .env.production.example)
  return (
    /\.md$/i.test(normalized) ||
    /(?:^|\/)\.env(?:\.[^/]+)?\.example$/.test(normalized)
  );
}
