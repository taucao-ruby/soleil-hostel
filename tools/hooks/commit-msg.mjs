#!/usr/bin/env node
import { promises as fs } from "node:fs";

import { getBypassEnvName, isHookBypassed, loadPolicy } from "./lib/hook-utils.mjs";

const policy = await loadPolicy();

if (isHookBypassed(policy)) {
  console.log(`[hooks] ${getBypassEnvName(policy)} is set, skipping commit message validation.`);
  process.exit(0);
}

const messageFile = process.argv[2];
if (!messageFile) {
  console.error("[hooks] commit-msg hook expected a commit message file path.");
  process.exit(1);
}

const raw = await fs.readFile(messageFile, "utf8");
const firstLine = raw
  .split(/\r?\n/)
  .map((line) => line.trim())
  .find((line) => line.length > 0);

if (!firstLine) {
  console.error("[hooks] Commit message cannot be empty.");
  process.exit(1);
}

if (firstLine.startsWith("Merge ") || firstLine.startsWith("Revert ")) {
  process.exit(0);
}

const conventionalCommitRegex =
  /^(feat|fix|chore|docs|refactor|test|build|ci|perf|revert)(\((backend|frontend|infra|docs)\))?(!)?: .+/;

if (!conventionalCommitRegex.test(firstLine)) {
  console.error("\n[hooks] Invalid commit message format.");
  console.error("Expected: <type>(<scope>)?: <subject>");
  console.error("Allowed types: feat, fix, chore, docs, refactor, test, build, ci, perf, revert");
  console.error("Allowed scopes: backend, frontend, infra, docs");
  console.error("\nExamples:");
  console.error("- feat(frontend): add booking form date validation");
  console.error("- fix(backend): enforce token revocation check");
  console.error("- docs: update deployment notes");
  process.exit(1);
}

