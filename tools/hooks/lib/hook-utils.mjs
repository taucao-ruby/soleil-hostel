import { spawn } from "node:child_process";
import { promises as fs } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { minimatch } from "minimatch";

const currentDir = path.dirname(fileURLToPath(import.meta.url));

export const REPO_ROOT = path.resolve(currentDir, "../../..");
export const POLICY_PATH = path.join(REPO_ROOT, "tools", "hooks", "hook-policy.json");

export const ALLOWED_BINARY_EXTENSIONS = new Set([
  ".png",
  ".jpg",
  ".jpeg",
  ".gif",
  ".webp",
  ".svg",
  ".ico",
  ".pdf",
  ".woff",
  ".woff2",
  ".ttf",
  ".eot",
]);

export async function loadPolicy() {
  const raw = await fs.readFile(POLICY_PATH, "utf8");
  return JSON.parse(raw.replace(/^\uFEFF/, ""));
}

export function getBypassEnvName(policy) {
  return policy.allow_bypass_env || "SKIP_HOOKS";
}

export function isHookBypassed(policy) {
  const envName = getBypassEnvName(policy);
  const value = process.env[envName];
  if (!value) {
    return false;
  }

  const normalized = String(value).trim().toLowerCase();
  return normalized === "1" || normalized === "true" || normalized === "yes";
}

export function normalizePath(relativePath) {
  return relativePath.replace(/\\/g, "/").replace(/^\.\//, "");
}

export function matchesBlockedPath(filePath, blockedPatterns) {
  const normalized = normalizePath(filePath);
  return blockedPatterns.some((pattern) =>
    minimatch(normalized, pattern, { dot: true, nocase: true }),
  );
}

export function compileSecretPatterns(policy) {
  return (policy.blocked_patterns || []).map((entry) => {
    const name = entry.name || "unnamed_pattern";
    const source = entry.source;
    const rawFlags = entry.flags || "i";
    const flags = rawFlags.replace(/g/g, "");

    return {
      name,
      regex: new RegExp(source, flags),
    };
  });
}

export async function getStagedFiles() {
  const result = await runCommand("git", [
    "diff",
    "--cached",
    "--name-only",
    "--diff-filter=ACMR",
  ]);

  if (result.code !== 0) {
    throw new Error(result.stderr.trim() || "Unable to read staged files.");
  }

  return result.stdout
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => normalizePath(line));
}

export async function runCommand(
  command,
  args,
  {
    cwd = REPO_ROOT,
    captureOutput = true,
    timeoutMs = 0,
    env = process.env,
  } = {},
) {
  return new Promise((resolve) => {
    const child = spawn(command, args, {
      cwd,
      env,
      shell: false,
      windowsHide: true,
      stdio: captureOutput ? ["ignore", "pipe", "pipe"] : "inherit",
    });

    let stdout = "";
    let stderr = "";
    let timedOut = false;

    if (captureOutput) {
      child.stdout.on("data", (chunk) => {
        stdout += chunk.toString();
      });

      child.stderr.on("data", (chunk) => {
        stderr += chunk.toString();
      });
    }

    let timeoutHandle = null;
    if (timeoutMs > 0) {
      timeoutHandle = setTimeout(() => {
        timedOut = true;
        child.kill("SIGTERM");
        setTimeout(() => {
          if (!child.killed) {
            child.kill("SIGKILL");
          }
        }, 2000).unref();
      }, timeoutMs);
    }

    child.on("error", (error) => {
      if (timeoutHandle) {
        clearTimeout(timeoutHandle);
      }

      resolve({
        code: -1,
        stdout,
        stderr,
        error,
        timedOut,
      });
    });

    child.on("close", (code) => {
      if (timeoutHandle) {
        clearTimeout(timeoutHandle);
      }

      resolve({
        code: typeof code === "number" ? code : -1,
        stdout,
        stderr,
        timedOut,
      });
    });
  });
}

export async function isLikelyBinaryFile(absolutePath) {
  const handle = await fs.open(absolutePath, "r");
  try {
    const buffer = Buffer.alloc(8192);
    const { bytesRead } = await handle.read(buffer, 0, buffer.length, 0);
    return buffer.subarray(0, bytesRead).includes(0);
  } finally {
    await handle.close();
  }
}

export function extractAddedLines(diffText) {
  return diffText
    .split(/\r?\n/)
    .filter((line) => line.startsWith("+") && !line.startsWith("+++"))
    .map((line) => line.slice(1));
}

export function resolveTargetCommand(policy, targetName) {
  const target = policy.verify_targets?.[targetName];
  if (!target) {
    throw new Error(`Unknown verify target: ${targetName}`);
  }

  const workingDirectory = path.resolve(REPO_ROOT, target.cwd || ".");

  return {
    targetName,
    command: target.command,
    args: target.args || [],
    cwd: workingDirectory,
    timeoutMs: target.timeout_ms || 0,
    optional: Boolean(target.optional),
  };
}

export function formatTargetCommand(target) {
  const command = [target.command, ...target.args].join(" ");
  const relativeCwd = normalizePath(path.relative(REPO_ROOT, target.cwd)) || ".";
  return relativeCwd === "." ? command : `cd ${relativeCwd} && ${command}`;
}

