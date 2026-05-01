#!/usr/bin/env node
/**
 * pnpm audit wrapper with allowlist + expiry enforcement.
 *
 * Batch 4 / 3C: the npm-audit CI job calls this script instead of bare
 * `pnpm audit`. Failure modes:
 *   1. New HIGH/CRITICAL advisory not in .audit-exceptions.json → fail.
 *   2. Allowlisted entry past its expiry date → fail.
 *
 * Run from the frontend/ directory (the CI step does `working-directory: ./frontend`).
 *
 * Usage:
 *   node ../scripts/audit-with-exceptions.mjs
 */

import { readFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { dirname, resolve } from "node:path";
import { spawn } from "node:child_process";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const exceptionsPath = resolve(process.cwd(), ".audit-exceptions.json");

async function readJson(path) {
  const raw = await readFile(path, "utf8");
  return JSON.parse(raw);
}

async function runAuditJson() {
  return new Promise((res, rej) => {
    let stdout = "";
    let stderr = "";

    // pnpm audit prints JSON to stdout when --json is passed; exit codes:
    //   0 = no advisories, 1 = advisories found.
    // We treat "advisories found" as non-fatal at this layer — we'll triage them ourselves.
    const child = spawn("pnpm", ["audit", "--audit-level=high", "--json"], {
      stdio: ["ignore", "pipe", "pipe"],
      shell: process.platform === "win32",
    });

    child.stdout.on("data", (d) => (stdout += d.toString()));
    child.stderr.on("data", (d) => (stderr += d.toString()));

    child.on("error", rej);
    child.on("close", (code) => {
      // Both 0 (clean) and 1 (advisories) are valid for our purposes.
      if (code !== 0 && code !== 1) {
        rej(new Error(`pnpm audit failed unexpectedly (exit ${code})\n${stderr}`));
        return;
      }
      res(stdout);
    });
  });
}

function parseAdvisories(jsonText) {
  // pnpm audit --json output shape:
  //   { advisories: { [id]: {...} }, metadata: {...} }
  // Older pnpm versions emit a stream of JSON lines. Handle both.
  const trimmed = jsonText.trim();
  if (trimmed === "") return [];

  let parsed;
  try {
    parsed = JSON.parse(trimmed);
  } catch {
    // Fall back to NDJSON parsing.
    const lines = trimmed.split(/\r?\n/).filter(Boolean);
    parsed = lines.map((l) => JSON.parse(l));
  }

  const advisories = [];
  const collect = (entry) => {
    if (!entry) return;
    if (entry.advisories && typeof entry.advisories === "object") {
      for (const adv of Object.values(entry.advisories)) {
        advisories.push(adv);
      }
    } else if (entry.id || entry.module_name || entry.package_name) {
      advisories.push(entry);
    }
  };

  if (Array.isArray(parsed)) parsed.forEach(collect);
  else collect(parsed);

  return advisories.filter((a) => {
    const sev = (a.severity || "").toLowerCase();
    return sev === "high" || sev === "critical";
  });
}

function ghsaId(adv) {
  return adv.github_advisory_id || adv.ghsa_id || adv.cve || adv.id?.toString() || "unknown";
}

async function main() {
  const exceptionsDoc = await readJson(exceptionsPath).catch((err) => {
    console.error(`Could not read ${exceptionsPath}:`, err.message);
    process.exit(1);
  });

  const exceptions = exceptionsDoc.exceptions || {};
  const today = new Date().toISOString().slice(0, 10);

  const expired = Object.entries(exceptions).filter(([, e]) => {
    return typeof e?.expiry === "string" && e.expiry < today;
  });

  if (expired.length > 0) {
    console.error("❌ Allowlisted advisories past their expiry date:");
    for (const [id, e] of expired) {
      console.error(`   - ${id} (expired ${e.expiry}): ${e.justification ?? "no justification"}`);
    }
    console.error("Renew the entry with fresh justification or remove it.");
    process.exit(1);
  }

  const auditJson = await runAuditJson();
  const advisories = parseAdvisories(auditJson);

  const newFindings = advisories.filter((a) => !(ghsaId(a) in exceptions));

  if (newFindings.length === 0) {
    console.log(`✅ npm audit clean (${advisories.length} HIGH/CRITICAL advisories, all allowlisted).`);
    process.exit(0);
  }

  console.error(`❌ ${newFindings.length} new HIGH/CRITICAL advisory(ies) not in .audit-exceptions.json:`);
  for (const adv of newFindings) {
    console.error(
      `   - ${ghsaId(adv)}  ${adv.severity}  ${adv.module_name ?? adv.package_name ?? "?"}: ${adv.title ?? ""}`,
    );
  }
  console.error("\nTo allowlist: add the GHSA id to frontend/.audit-exceptions.json with severity, justification, expiry (YYYY-MM-DD).");
  process.exit(1);
}

await main();
