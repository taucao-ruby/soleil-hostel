import { existsSync } from "node:fs";
import { promises as fs } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { spawn } from "node:child_process";

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { CallToolRequestSchema, ListToolsRequestSchema } from "@modelcontextprotocol/sdk/types.js";

type AllowedCommand = {
  cwd: string;
  command: string;
  args: string[];
  timeout_ms?: number;
};

type Policy = {
  allowed_commands: Record<string, AllowedCommand>;
  blocked_paths: string[];
  blocked_file_patterns: string[];
  max_file_size: number;
  search: {
    max_results: number;
    snippet_chars: number;
    max_files_scanned: number;
  };
  timeouts: {
    default_ms: number;
    max_ms: number;
  };
};

type SearchArgs = {
  query: string;
  paths?: string[];
  max_results?: number;
};

type RunVerifyArgs = {
  verify_target: string;
  timeout_ms?: number;
  cancel_after_ms?: number;
};

type SearchMatch = {
  relative_path: string;
  line: number;
  snippet: string;
};

type RunResult = {
  verify_target: string;
  command: string;
  cwd: string;
  exit_code: number;
  duration_ms: number;
  timed_out: boolean;
  cancelled: boolean;
  status: "passed" | "failed" | "timed_out" | "cancelled";
  stdout: string;
  stderr: string;
};

const SERVER_NAME = "soleil-mcp";
const SERVER_VERSION = "0.1.0";

const moduleDir = path.dirname(fileURLToPath(import.meta.url));
const projectDir = resolveProjectDir(moduleDir);
const repoRoot = findRepoRoot(projectDir);
const policyPath = path.join(projectDir, "policy.json");
const policy = await loadPolicy(policyPath);
const blockedFileRegexes = policy.blocked_file_patterns.map((pattern) => new RegExp(pattern, "i"));

const transport = new StdioServerTransport();
const server = new Server(
  {
    name: SERVER_NAME,
    version: SERVER_VERSION,
  },
  {
    capabilities: {
      tools: {},
    },
  },
);

server.setRequestHandler(ListToolsRequestSchema, async () => {
  const verifyTargets = Object.keys(policy.allowed_commands).sort();

  return {
    tools: [
      {
        name: "repo_overview",
        description: "Read-only repository overview with key paths and verified commands.",
        inputSchema: {
          type: "object",
          properties: {},
          additionalProperties: false,
        },
      },
      {
        name: "read_file",
        description: "Read a safe text file from the repository (denylist and size limits enforced).",
        inputSchema: {
          type: "object",
          properties: {
            relative_path: {
              type: "string",
              description: "Workspace-relative path to read.",
            },
          },
          required: ["relative_path"],
          additionalProperties: false,
        },
      },
      {
        name: "search",
        description: "Search text in safe repository files and return matching snippets.",
        inputSchema: {
          type: "object",
          properties: {
            query: {
              type: "string",
              description: "Case-insensitive text query.",
            },
            paths: {
              type: "array",
              items: { type: "string" },
              description: "Optional list of relative paths (files/directories) to scope search.",
            },
            max_results: {
              type: "integer",
              minimum: 1,
              description: `Optional result limit (capped by policy: ${policy.search.max_results}).`,
            },
          },
          required: ["query"],
          additionalProperties: false,
        },
      },
      {
        name: "run_verify",
        description: "Run a verification target from allowlisted commands only.",
        inputSchema: {
          type: "object",
          properties: {
            verify_target: {
              type: "string",
              enum: verifyTargets,
              description: "Verification target defined in policy.json allowed_commands.",
            },
            timeout_ms: {
              type: "integer",
              minimum: 1,
              description: `Optional timeout override in ms (max ${policy.timeouts.max_ms}).`,
            },
            cancel_after_ms: {
              type: "integer",
              minimum: 1,
              description: "Optional cancellation timer in ms for deterministic cancellation testing.",
            },
          },
          required: ["verify_target"],
          additionalProperties: false,
        },
      },
      {
        name: "project_invariants",
        description: "Return Soleil Hostel invariants, warnings, and source pointers.",
        inputSchema: {
          type: "object",
          properties: {},
          additionalProperties: false,
        },
      },
    ],
  };
});

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  try {
    const toolName = request.params.name;
    const args = (request.params.arguments ?? {}) as Record<string, unknown>;

    if (toolName === "repo_overview") {
      return ok(await getRepoOverview());
    }

    if (toolName === "read_file") {
      const relativePath = readRequiredString(args, "relative_path");
      const result = await readFileSafe(relativePath);
      return ok(result);
    }

    if (toolName === "search") {
      const query = readRequiredString(args, "query");
      const paths = readOptionalStringArray(args, "paths");
      const maxResults = readOptionalInt(args, "max_results");
      const result = await searchSafe({ query, paths, max_results: maxResults });
      return ok(result);
    }

    if (toolName === "run_verify") {
      const verifyTarget = readRequiredString(args, "verify_target");
      const timeoutMs = readOptionalInt(args, "timeout_ms");
      const cancelAfterMs = readOptionalInt(args, "cancel_after_ms");
      const result = await runVerify({
        verify_target: verifyTarget,
        timeout_ms: timeoutMs,
        cancel_after_ms: cancelAfterMs,
      });
      return ok(result);
    }

    if (toolName === "project_invariants") {
      return ok(await getProjectInvariants());
    }

    return err(`Unknown tool: ${toolName}`);
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    return err(message);
  }
});

async function main(): Promise<void> {
  if (process.argv[2] === "--self-test") {
    const target = process.argv[3] ?? "docker_compose_config";
    try {
      const result = await runVerify({ verify_target: target });
      process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
      process.exitCode = result.exit_code === 0 ? 0 : 1;
      return;
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      process.stderr.write(`${message}\n`);
      process.exitCode = 1;
      return;
    }
  }

  await server.connect(transport);
}

await main();

async function getRepoOverview(): Promise<Record<string, unknown>> {
  const rootPackage = await readJsonIfExists(path.join(repoRoot, "package.json"));
  const frontendPackage = await readJsonIfExists(path.join(repoRoot, "frontend", "package.json"));
  const composerJson = await readJsonIfExists(path.join(repoRoot, "backend", "composer.json"));
  const testsWorkflow = await readTextIfExists(path.join(repoRoot, ".github", "workflows", "tests.yml"));
  const deployWorkflow = await readTextIfExists(path.join(repoRoot, ".github", "workflows", "deploy.yml"));

  const workflowNodeVersion = matchFirst(testsWorkflow, /NODE_VERSION:\s*"?([0-9.]+)"?/);
  const workflowPhpVersion = matchFirst(testsWorkflow, /PHP_VERSION:\s*"?([0-9.]+)"?/);

  const verifyCommands = Object.entries(policy.allowed_commands)
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([target, command]) => ({
      verify_target: target,
      command: formatCommand(command),
      cwd: command.cwd,
      timeout_ms: command.timeout_ms ?? policy.timeouts.default_ms,
    }));

  const keyPaths = [
    "backend",
    "frontend",
    "docs",
    ".github/workflows",
    "AGENTS.md",
    "PROJECT_STATUS.md",
    "docs/COMPACT.md",
    "docs/WORKLOG.md",
  ].filter((entry) => existsSync(path.join(repoRoot, entry)));

  return {
    server: {
      name: SERVER_NAME,
      version: SERVER_VERSION,
      policy_path: toPosix(path.relative(repoRoot, policyPath)),
      mode: "safe-readonly-with-allowlisted-verify",
    },
    repository_root: repoRoot,
    key_paths: keyPaths,
    package_management: {
      root: {
        package_json: existsSync(path.join(repoRoot, "package.json")),
        package_lock: existsSync(path.join(repoRoot, "package-lock.json")),
        pnpm_lock: existsSync(path.join(repoRoot, "pnpm-lock.yaml")),
        yarn_lock: existsSync(path.join(repoRoot, "yarn.lock")),
      },
      frontend: {
        package_json: existsSync(path.join(repoRoot, "frontend", "package.json")),
        package_lock: existsSync(path.join(repoRoot, "frontend", "package-lock.json")),
        pnpm_lock: existsSync(path.join(repoRoot, "frontend", "pnpm-lock.yaml")),
        yarn_lock: existsSync(path.join(repoRoot, "frontend", "yarn.lock")),
      },
      notes: [
        "CI workflows use pnpm (frontend) and composer (backend).",
        "Local verified commands in project docs use npx/npm for frontend checks.",
      ],
    },
    runtime_constraints: {
      node_engine_root: rootPackage?.engines?.node ?? null,
      node_engine_frontend: frontendPackage?.engines?.node ?? null,
      node_version_ci: workflowNodeVersion ?? null,
      php_constraint_backend: composerJson?.require?.php ?? null,
      php_version_ci: workflowPhpVersion ?? null,
    },
    docs_conventions: {
      docs_index: existsSync(path.join(repoRoot, "docs", "README.md")) ? "docs/README.md" : null,
      agent_memory: existsSync(path.join(repoRoot, "AGENTS.md")) ? "AGENTS.md" : null,
      compact_memory: existsSync(path.join(repoRoot, "docs", "COMPACT.md")) ? "docs/COMPACT.md" : null,
      worklog: existsSync(path.join(repoRoot, "docs", "WORKLOG.md")) ? "docs/WORKLOG.md" : null,
    },
    ci_workflows: {
      tests: existsSync(path.join(repoRoot, ".github", "workflows", "tests.yml"))
        ? ".github/workflows/tests.yml"
        : null,
      deploy: existsSync(path.join(repoRoot, ".github", "workflows", "deploy.yml"))
        ? ".github/workflows/deploy.yml"
        : null,
      notes: summarizeWorkflowCommands(testsWorkflow, deployWorkflow),
    },
    how_to_verify: verifyCommands,
  };
}

async function readFileSafe(relativePath: string): Promise<Record<string, unknown>> {
  const target = resolveRepoPath(relativePath);
  guardBlockedPath(target.relative);

  const stat = await fs.stat(target.absolute).catch(() => null);
  if (!stat || !stat.isFile()) {
    throw new Error(`File not found: ${relativePath}`);
  }

  if (stat.size > policy.max_file_size) {
    throw new Error(
      `File exceeds max_file_size (${policy.max_file_size} bytes): ${target.relative} (${stat.size} bytes)`,
    );
  }

  const buffer = await fs.readFile(target.absolute);
  if (buffer.includes(0)) {
    throw new Error(`Binary files are not supported: ${target.relative}`);
  }

  const content = buffer.toString("utf8");

  return {
    relative_path: target.relative,
    size_bytes: stat.size,
    content,
  };
}

async function searchSafe(args: SearchArgs): Promise<Record<string, unknown>> {
  const query = args.query.trim();
  if (!query) {
    throw new Error("query is required");
  }

  const requestedMax = args.max_results ?? policy.search.max_results;
  const maxResults = Math.max(1, Math.min(requestedMax, policy.search.max_results));

  const targetPaths = (args.paths && args.paths.length > 0
    ? args.paths
    : ["backend", "frontend", "docs", ".github", "AGENTS.md", "PROJECT_STATUS.md", "README.md"]
  ).map((entry) => resolveRepoPath(entry));

  for (const target of targetPaths) {
    guardBlockedPath(target.relative);
  }

  const files = await collectSearchFiles(targetPaths.map((entry) => entry.absolute));
  const matches: SearchMatch[] = [];
  const queryLower = query.toLowerCase();

  for (const filePath of files) {
    if (matches.length >= maxResults) {
      break;
    }

    const relative = normalizeRelative(path.relative(repoRoot, filePath));
    const text = await fs.readFile(filePath, "utf8").catch(() => null);
    if (text === null || text.includes("\u0000")) {
      continue;
    }

    const lines = text.split(/\r?\n/);
    for (let index = 0; index < lines.length; index += 1) {
      if (matches.length >= maxResults) {
        break;
      }

      const line = lines[index];
      if (!line.toLowerCase().includes(queryLower)) {
        continue;
      }

      const trimmed = line.trim();
      const snippet =
        trimmed.length > policy.search.snippet_chars
          ? `${trimmed.slice(0, policy.search.snippet_chars)}...`
          : trimmed;

      matches.push({
        relative_path: relative,
        line: index + 1,
        snippet,
      });
    }
  }

  return {
    query,
    max_results: maxResults,
    result_count: matches.length,
    truncated: matches.length >= maxResults,
    searched_files: files.length,
    matches,
  };
}

async function runVerify(args: RunVerifyArgs): Promise<RunResult> {
  const target = args.verify_target;
  const definition = policy.allowed_commands[target];
  if (!definition) {
    throw new Error(`verify_target is not allowlisted: ${target}`);
  }

  const timeoutMs = clampTimeout(args.timeout_ms ?? definition.timeout_ms ?? policy.timeouts.default_ms);
  const cancelAfterMs = args.cancel_after_ms !== undefined ? Math.max(1, args.cancel_after_ms) : null;

  const cwdResolved = resolveRepoPath(definition.cwd);
  guardBlockedPath(cwdResolved.relative, { allowRoot: true });

  const cwdAbsolute = cwdResolved.absolute;
  const command = resolveCommand(definition.command, cwdAbsolute);
  const commandArgs = [...definition.args];

  const startedAt = Date.now();
  let timedOut = false;
  let cancelled = false;

  const result = await new Promise<{ exitCode: number; stdout: string; stderr: string }>((resolve, reject) => {
    const child = spawn(command, commandArgs, {
      cwd: cwdAbsolute,
      shell: false,
      windowsHide: true,
    });

    let stdout = "";
    let stderr = "";

    const onStdout = (chunk: Buffer | string): void => {
      stdout = appendWithLimit(stdout, chunk.toString());
    };

    const onStderr = (chunk: Buffer | string): void => {
      stderr = appendWithLimit(stderr, chunk.toString());
    };

    child.stdout.on("data", onStdout);
    child.stderr.on("data", onStderr);

    const terminate = (): void => {
      if (child.killed) {
        return;
      }
      child.kill("SIGTERM");
      setTimeout(() => {
        if (!child.killed) {
          child.kill("SIGKILL");
        }
      }, 2000).unref();
    };

    const timeoutHandle = setTimeout(() => {
      timedOut = true;
      terminate();
    }, timeoutMs);

    const cancelHandle =
      cancelAfterMs !== null
        ? setTimeout(() => {
            cancelled = true;
            terminate();
          }, cancelAfterMs)
        : null;

    const onSignal = (): void => {
      cancelled = true;
      terminate();
    };

    process.once("SIGINT", onSignal);
    process.once("SIGTERM", onSignal);

    child.once("error", (error) => {
      clearTimeout(timeoutHandle);
      if (cancelHandle) {
        clearTimeout(cancelHandle);
      }
      process.off("SIGINT", onSignal);
      process.off("SIGTERM", onSignal);
      reject(error);
    });

    child.once("close", (code) => {
      clearTimeout(timeoutHandle);
      if (cancelHandle) {
        clearTimeout(cancelHandle);
      }
      process.off("SIGINT", onSignal);
      process.off("SIGTERM", onSignal);

      resolve({
        exitCode: code ?? -1,
        stdout: redactSecrets(stdout),
        stderr: redactSecrets(stderr),
      });
    });
  });

  const durationMs = Date.now() - startedAt;
  const status: RunResult["status"] = cancelled
    ? "cancelled"
    : timedOut
      ? "timed_out"
      : result.exitCode === 0
        ? "passed"
        : "failed";

  return {
    verify_target: target,
    command: formatCommand(definition),
    cwd: cwdResolved.relative,
    exit_code: result.exitCode,
    duration_ms: durationMs,
    timed_out: timedOut,
    cancelled,
    status,
    stdout: result.stdout,
    stderr: result.stderr,
  };
}

async function getProjectInvariants(): Promise<Record<string, unknown>> {
  const verifyTargets = Object.keys(policy.allowed_commands).sort();

  return {
    snapshot: {
      as_of: "2026-02-11",
      branch: "dev",
      branch_alignment: "dev at 096adfa (8 commits ahead of main at 712478e)",
      verified_commands: [
        "cd backend && php artisan test (722 tests, 2012 assertions)",
        "cd frontend && npx tsc --noEmit",
        "cd frontend && npx vitest run (145 tests)",
        "docker compose config",
      ],
      known_non_blocking_warnings: [
        "PHPUnit doc-comment metadata deprecation warnings (PASS)",
        "Vitest act(...) and non-boolean DOM attribute warnings (PASS)",
      ],
    },
    invariants: [
      {
        id: "booking_overlap",
        rule: "Booking overlap logic must use half-open intervals [check_in, check_out) with active statuses pending/confirmed.",
        sources: existingPaths([
          "backend/app/Models/Booking.php",
          "backend/database/migrations/2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php",
          "docs/DATABASE.md",
        ]),
      },
      {
        id: "postgres_exclusion_constraint",
        rule: "Production overlap enforcement depends on PostgreSQL EXCLUDE USING gist with deleted_at IS NULL filter.",
        sources: existingPaths([
          "backend/database/migrations/2026_02_12_000001_fix_overlapping_bookings_constraint_soft_deletes.php",
          "backend/database/migrations/2025_12_18_000000_optimize_booking_indexes.php",
          "docs/DATABASE.md",
        ]),
      },
      {
        id: "sqlite_test_mode",
        rule: "Default tests use SQLite in-memory; Postgres-only behavior must be validated against PostgreSQL.",
        sources: existingPaths(["backend/phpunit.xml", "AGENTS.md", ".github/workflows/tests.yml"]),
      },
      {
        id: "auth_tokens",
        rule: "Sanctum token model includes custom security columns for identifier/hash binding, expiry, revocation, and device signals.",
        sources: existingPaths([
          "backend/app/Models/PersonalAccessToken.php",
          "backend/database/migrations/2025_11_20_000100_add_token_expiration_to_personal_access_tokens.php",
          "backend/database/migrations/2025_11_21_150000_add_token_security_columns.php",
          "AGENTS.md",
        ]),
      },
      {
        id: "booking_audit",
        rule: "Bookings must preserve soft-delete and cancellation audit fields (deleted_at/deleted_by, cancelled_by/cancellation_reason).",
        sources: existingPaths([
          "backend/app/Models/Booking.php",
          "backend/database/migrations/2025_12_18_100000_add_soft_deletes_to_bookings.php",
          "backend/database/migrations/2026_01_11_000001_add_payment_fields_to_bookings.php",
          "backend/database/migrations/2026_02_10_091954_add_cancellation_reason_to_bookings_table.php",
        ]),
      },
      {
        id: "locking",
        rule: "Room updates use optimistic lock_version checks; booking conflict/cancellation paths use pessimistic SELECT FOR UPDATE.",
        sources: existingPaths([
          "backend/app/Services/RoomService.php",
          "backend/app/Services/CreateBookingService.php",
          "backend/app/Services/CancellationService.php",
          "docs/backend/TRANSACTION_ISOLATION.md",
        ]),
      },
    ],
    verify_targets: verifyTargets,
  };
}

function resolveProjectDir(currentModuleDir: string): string {
  const candidates = [currentModuleDir, path.resolve(currentModuleDir, "..")];
  for (const candidate of candidates) {
    if (existsSync(path.join(candidate, "policy.json"))) {
      return candidate;
    }
  }
  throw new Error("Could not locate mcp/soleil-mcp project directory (policy.json not found).");
}

function findRepoRoot(startDir: string): string {
  let current = startDir;

  for (let index = 0; index < 10; index += 1) {
    const hasBackend = existsSync(path.join(current, "backend"));
    const hasFrontend = existsSync(path.join(current, "frontend"));
    const hasDocs = existsSync(path.join(current, "docs"));

    if (hasBackend && hasFrontend && hasDocs) {
      return current;
    }

    const parent = path.dirname(current);
    if (parent === current) {
      break;
    }

    current = parent;
  }

  throw new Error("Could not locate repository root from mcp/soleil-mcp directory.");
}

async function loadPolicy(filePath: string): Promise<Policy> {
  const raw = await fs.readFile(filePath, "utf8");
  const parsed = JSON.parse(raw.replace(/^\uFEFF/, "")) as Partial<Policy>;

  if (!parsed.allowed_commands || typeof parsed.allowed_commands !== "object") {
    throw new Error("Invalid policy: allowed_commands is required");
  }

  if (!Array.isArray(parsed.blocked_paths) || !Array.isArray(parsed.blocked_file_patterns)) {
    throw new Error("Invalid policy: blocked_paths and blocked_file_patterns are required arrays");
  }

  if (typeof parsed.max_file_size !== "number") {
    throw new Error("Invalid policy: max_file_size must be a number");
  }

  if (!parsed.search || !parsed.timeouts) {
    throw new Error("Invalid policy: search and timeouts sections are required");
  }

  return parsed as Policy;
}

function resolveRepoPath(inputPath: string): { absolute: string; relative: string } {
  if (!inputPath || inputPath.trim() === "") {
    throw new Error("Path is required");
  }

  if (path.isAbsolute(inputPath)) {
    throw new Error("Absolute paths are not allowed");
  }

  const absolute = path.resolve(repoRoot, inputPath);
  const relative = normalizeRelative(path.relative(repoRoot, absolute));

  if (!relative || relative === "") {
    return { absolute, relative: "." };
  }

  if (relative.startsWith("../") || relative === "..") {
    throw new Error("Path escapes repository root");
  }

  return { absolute, relative };
}

function normalizeRelative(relativePath: string): string {
  return toPosix(relativePath).replace(/^\.\//, "");
}

function toPosix(value: string): string {
  return value.replace(/\\/g, "/");
}

function guardBlockedPath(relativePath: string, options?: { allowRoot?: boolean }): void {
  if (options?.allowRoot && relativePath === ".") {
    return;
  }

  if (relativePath === ".") {
    throw new Error("Root path is not a readable file target");
  }

  const normalized = normalizeRelative(relativePath);
  const segments = normalized.split("/");

  for (const blocked of policy.blocked_paths) {
    const blockedNormalized = normalizeRelative(blocked);
    if (blockedNormalized.includes("/")) {
      if (normalized === blockedNormalized || normalized.startsWith(`${blockedNormalized}/`)) {
        throw new Error(`Path is blocked by policy: ${normalized}`);
      }
      continue;
    }

    if (segments.includes(blockedNormalized)) {
      throw new Error(`Path is blocked by policy: ${normalized}`);
    }
  }

  const basename = path.posix.basename(normalized);
  for (const regex of blockedFileRegexes) {
    if (regex.test(basename) || regex.test(normalized)) {
      throw new Error(`Path matches blocked file pattern: ${normalized}`);
    }
  }
}

async function collectSearchFiles(startPaths: string[]): Promise<string[]> {
  const files: string[] = [];
  const stack = [...startPaths];
  const seen = new Set<string>();

  while (stack.length > 0 && files.length < policy.search.max_files_scanned) {
    const current = stack.pop();
    if (!current) {
      continue;
    }

    const realPath = path.resolve(current);
    if (seen.has(realPath)) {
      continue;
    }
    seen.add(realPath);

    const relative = normalizeRelative(path.relative(repoRoot, realPath)) || ".";

    try {
      guardBlockedPath(relative, { allowRoot: true });
    } catch {
      continue;
    }

    const stat = await fs.lstat(realPath).catch(() => null);
    if (!stat) {
      continue;
    }

    if (stat.isSymbolicLink()) {
      continue;
    }

    if (stat.isDirectory()) {
      const entries = await fs.readdir(realPath, { withFileTypes: true }).catch(() => []);
      entries.sort((left, right) => left.name.localeCompare(right.name));

      for (let index = entries.length - 1; index >= 0; index -= 1) {
        const entry = entries[index];
        stack.push(path.join(realPath, entry.name));
      }

      continue;
    }

    if (stat.isFile()) {
      if (stat.size > policy.max_file_size) {
        continue;
      }
      files.push(realPath);
    }
  }

  return files
    .map((entry) => path.resolve(entry))
    .sort((left, right) => normalizeRelative(path.relative(repoRoot, left)).localeCompare(normalizeRelative(path.relative(repoRoot, right))));
}

function readRequiredString(args: Record<string, unknown>, key: string): string {
  const value = args[key];
  if (typeof value !== "string" || value.trim() === "") {
    throw new Error(`${key} must be a non-empty string`);
  }
  return value;
}

function readOptionalStringArray(args: Record<string, unknown>, key: string): string[] | undefined {
  const value = args[key];
  if (value === undefined) {
    return undefined;
  }
  if (!Array.isArray(value) || value.some((entry) => typeof entry !== "string")) {
    throw new Error(`${key} must be an array of strings`);
  }
  return value as string[];
}

function readOptionalInt(args: Record<string, unknown>, key: string): number | undefined {
  const value = args[key];
  if (value === undefined) {
    return undefined;
  }
  if (typeof value !== "number" || !Number.isFinite(value) || !Number.isInteger(value) || value < 1) {
    throw new Error(`${key} must be an integer >= 1`);
  }
  return value;
}

function ok(payload: unknown): { content: Array<{ type: "text"; text: string }> } {
  return {
    content: [
      {
        type: "text",
        text: JSON.stringify(payload, null, 2),
      },
    ],
  };
}

function err(message: string): { content: Array<{ type: "text"; text: string }>; isError: true } {
  return {
    content: [
      {
        type: "text",
        text: message,
      },
    ],
    isError: true,
  };
}

function formatCommand(command: AllowedCommand): string {
  const base = [command.command, ...command.args].join(" ");
  if (command.cwd === ".") {
    return base;
  }
  return `cd ${command.cwd} && ${base}`;
}

function resolveCommand(command: string, cwdAbsolute: string): string {
  if (command.includes("/") || command.includes("\\")) {
    return path.resolve(cwdAbsolute, command);
  }
  return command;
}

function clampTimeout(value: number): number {
  if (!Number.isFinite(value) || value <= 0) {
    return policy.timeouts.default_ms;
  }
  return Math.min(Math.max(1, Math.trunc(value)), policy.timeouts.max_ms);
}

function appendWithLimit(current: string, addition: string, limit = 1_000_000): string {
  const next = current + addition;
  if (next.length <= limit) {
    return next;
  }
  return next.slice(next.length - limit);
}

function redactSecrets(output: string): string {
  const patterns: Array<{ pattern: RegExp; replace: string }> = [
    {
      pattern:
        /((?:APP_KEY|DB_PASSWORD|REDIS_PASSWORD|POSTGRES_PASSWORD|PASSWORD|SECRET|TOKEN|API_KEY)\s*[:=]\s*)("?[^\r\n"']+"?)/gi,
      replace: "$1[REDACTED]",
    },
    {
      pattern: /(--requirepass\s+)([^\s]+)/gi,
      replace: "$1[REDACTED]",
    },
    {
      pattern: /(redis-cli\s+-a\s+)([^\s]+)/gi,
      replace: "$1[REDACTED]",
    },
    {
      pattern: /(--requirepass\s*\r?\n\s*-\s*)([^\s]+)/gi,
      replace: "$1[REDACTED]",
    },
    {
      pattern: /\b[^\s"']*secret[^\s"']*\b/gi,
      replace: "[REDACTED]",
    },
    {
      pattern: /(Bearer\s+)[A-Za-z0-9\-._~+/]+=*/gi,
      replace: "$1[REDACTED]",
    },
    {
      pattern: /\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/g,
      replace: "[REDACTED_JWT]",
    },
    {
      pattern: /\bsk_(live|test)_[A-Za-z0-9]+\b/gi,
      replace: "[REDACTED_KEY]",
    },
  ];

  return patterns.reduce((acc, item) => acc.replace(item.pattern, item.replace), output);
}
async function readJsonIfExists(filePath: string): Promise<any> {
  if (!existsSync(filePath)) {
    return null;
  }
  const text = await fs.readFile(filePath, "utf8");
  return JSON.parse(text);
}

async function readTextIfExists(filePath: string): Promise<string> {
  if (!existsSync(filePath)) {
    return "";
  }
  return fs.readFile(filePath, "utf8");
}

function matchFirst(source: string, regex: RegExp): string | null {
  const match = source.match(regex);
  return match ? match[1] : null;
}

function summarizeWorkflowCommands(testsWorkflow: string, deployWorkflow: string): string[] {
  const notes: string[] = [];

  if (testsWorkflow.includes("php artisan test")) {
    notes.push("tests.yml runs backend tests with php artisan test");
  }

  if (testsWorkflow.includes("pnpm test:unit") || testsWorkflow.includes("vitest")) {
    notes.push("tests.yml runs frontend unit tests (Vitest via pnpm)");
  }

  if (testsWorkflow.includes("pnpm run lint") || deployWorkflow.includes("pnpm run lint")) {
    notes.push("CI/CD includes frontend lint via pnpm run lint");
  }

  if (testsWorkflow.includes("vendor/bin/pint --test")) {
    notes.push("tests.yml includes backend lint/style via vendor/bin/pint --test");
  }

  return notes;
}

function existingPaths(pathsToCheck: string[]): string[] {
  return pathsToCheck.filter((entry) => existsSync(path.join(repoRoot, entry)));
}




