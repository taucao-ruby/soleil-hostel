#!/usr/bin/env node

import net from "node:net";

import {
  formatTargetCommand,
  getBypassEnvName,
  isHookBypassed,
  loadPolicy,
  resolveTargetCommand,
  runCommand,
} from "./lib/hook-utils.mjs";

// phpunit.xml defaults the suite to a local PostgreSQL on 127.0.0.1:5432. The
// hook checks the same target (honoring an explicit DB_HOST/DB_PORT override, as
// PHPUnit's <env> elements do not force over an existing environment value).
const DB_PREFLIGHT_TIMEOUT_MS = 2000;

const policy = await loadPolicy();
const dryRun = process.argv.includes("--dry-run");

if (isHookBypassed(policy)) {
  console.log(
    `[hooks] ${getBypassEnvName(policy)} is set, skipping pre-push verification.`,
  );
  process.exit(0);
}

const changedFiles = await detectChangedFiles();

let targetsToRun = [];
if (changedFiles === null) {
  targetsToRun = ["backend_tests", "frontend_typecheck", "frontend_unit_tests"];
  console.log(
    "[hooks] Could not resolve diff base; running full verification baseline.",
  );
} else {
  const codeChanges = changedFiles.filter((file) => !isNonCodePath(file));
  const hasBackendChanges = codeChanges.some((file) =>
    file.startsWith("backend/"),
  );
  const hasFrontendChanges = codeChanges.some((file) =>
    file.startsWith("frontend/"),
  );
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
  console.log(
    "[hooks] No backend/frontend/compose changes detected; skipping pre-push verification.",
  );
  process.exit(0);
}

// FIX 1 — fail fast on a dead test DB. `php artisan test` blocks for many
// minutes when PostgreSQL is unreachable, so probe it cheaply first and abort
// the push with an actionable message instead of letting the suite hang.
if (targetsToRun.includes("backend_tests")) {
  const dbHost = process.env.DB_HOST || "127.0.0.1";
  const dbPort = Number(process.env.DB_PORT || "5432");

  if (dryRun) {
    console.log(
      `\n[hooks] Would preflight test DB reachability on ${dbHost}:${dbPort} before backend_tests.`,
    );
  } else {
    const probe = await checkTestDbReachable(
      dbHost,
      dbPort,
      DB_PREFLIGHT_TIMEOUT_MS,
    );
    if (!probe.ok) {
      console.error(
        `[hooks] Pre-push: test database not reachable on ${dbHost}:${dbPort} — run \`docker compose up -d db\` and retry.`,
      );
      if (probe.reason) {
        console.error(`[hooks]   Reason: ${probe.reason}`);
      }
      process.exit(1);
    }
    console.log(`[hooks] Test DB reachable on ${dbHost}:${dbPort}.`);
  }
}

for (const targetName of targetsToRun) {
  const target = resolveTargetCommand(policy, targetName);
  console.log(
    `\n[hooks] Running ${targetName}: ${formatTargetCommand(target)}`,
  );

  if (dryRun) {
    continue;
  }

  const result = await runCommand(target.command, target.args, {
    cwd: target.cwd,
    captureOutput: false,
    timeoutMs: target.timeoutMs,
    env: target.env,
  });

  if (result.error?.code === "ENOENT" && target.optional) {
    console.log(
      `[hooks] Skipping optional target '${targetName}' because command was not found.`,
    );
    continue;
  }

  if (result.timedOut) {
    console.error(
      `[hooks] '${targetName}' timed out after ${target.timeoutMs}ms.`,
    );
    process.exit(1);
  }

  if (result.code !== 0) {
    console.error(
      `[hooks] '${targetName}' failed with exit code ${result.code}.`,
    );
    process.exit(result.code || 1);
  }
}

if (dryRun) {
  console.log("\n[hooks] Dry run completed.");
  process.exit(0);
}

console.log("\n[hooks] pre-push verification passed.");

async function detectChangedFiles() {
  const base = await resolveDiffBase();
  if (!base) {
    return null;
  }

  // FIX 2 — when the base is the branch's own remote tracking ref, compare HEAD's
  // tree against it directly (two-dot) so a tree that is byte-identical to what
  // the remote already holds produces an empty diff and skips the suite (e.g.
  // re-pushing or force-pushing an already-tested commit). For the integration
  // fallback (origin/dev|main on a brand-new branch) keep the merge-base
  // (three-dot) behavior so the branch still runs its suites.
  const range = base.threeDot ? `${base.ref}...HEAD` : `${base.ref}..HEAD`;

  const diffResult = await runCommand("git", ["diff", "--name-only", range]);
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
  // Prefer the branch's own remote tracking ref — what the remote actually holds
  // for the branch being pushed. Diffing HEAD's tree directly against it lets us
  // skip suites whose source tree already matches the remote.
  const upstreamResult = await runCommand("git", [
    "rev-parse",
    "--abbrev-ref",
    "--symbolic-full-name",
    "@{u}",
  ]);
  if (upstreamResult.code === 0) {
    const upstream = upstreamResult.stdout.trim();
    if (upstream) {
      return { ref: upstream, threeDot: false };
    }
  }

  const branchResult = await runCommand("git", [
    "rev-parse",
    "--abbrev-ref",
    "HEAD",
  ]);
  const branch = branchResult.code === 0 ? branchResult.stdout.trim() : "";

  if (branch && branch !== "HEAD") {
    const originBranch = `origin/${branch}`;
    const exists = await runCommand("git", [
      "show-ref",
      "--verify",
      "--quiet",
      `refs/remotes/${originBranch}`,
    ]);
    if (exists.code === 0) {
      return { ref: originBranch, threeDot: false };
    }
  }

  // No remote counterpart for this branch (no upstream / brand-new branch):
  // keep the existing behavior and scope the diff to the merge-base with the
  // integration branch so a fresh feature branch still runs its suites.
  for (const candidate of ["origin/dev", "origin/main"]) {
    const exists = await runCommand("git", [
      "show-ref",
      "--verify",
      "--quiet",
      `refs/remotes/${candidate}`,
    ]);
    if (exists.code === 0) {
      return { ref: candidate, threeDot: true };
    }
  }

  return null;
}

// Fast TCP reachability probe for the PostgreSQL test database. Returns as soon
// as the connection is refused (server down) and is capped by `timeoutMs` for
// the black-hole case, so the push never waits on a dead DB.
function checkTestDbReachable(host, port, timeoutMs) {
  return new Promise((resolve) => {
    const socket = new net.Socket();
    let settled = false;

    const done = (ok, reason) => {
      if (settled) {
        return;
      }
      settled = true;
      socket.destroy();
      resolve({ ok, reason });
    };

    socket.setTimeout(timeoutMs);
    socket.once("connect", () => done(true));
    socket.once("timeout", () =>
      done(false, `connection timed out after ${timeoutMs}ms`),
    );
    socket.once("error", (error) => done(false, error.message));
    socket.connect(port, host);
  });
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
