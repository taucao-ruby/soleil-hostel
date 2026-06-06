#!/usr/bin/env node
// @ts-check
/**
 * Ingest one Playwright JSON result file into the append-only NDJSON ledger.
 *
 * Usage:
 *   node scripts/flake/ingest.mjs \
 *     --file test-results/results.json \
 *     --commit "$GITHUB_SHA" --branch "$GITHUB_REF_NAME" \
 *     --ledger tests/e2e/flake-history.ndjson [--run-id <id>] [--prune]
 *
 * Idempotent: re-ingesting the same run_id is a no-op (guards against a CI
 * step retry double-counting the same run).
 */
import fs from 'node:fs';
import path from 'node:path';
import { parseArgs } from 'node:util';
import { extractRows, readLedger, pruneOld } from './lib.mjs';

const { values: args } = parseArgs({
  options: {
    file: { type: 'string' },
    commit: { type: 'string', default: 'unknown' },
    branch: { type: 'string', default: 'unknown' },
    ledger: { type: 'string', default: 'tests/e2e/flake-history.ndjson' },
    'run-id': { type: 'string' },
    prune: { type: 'boolean', default: false },
  },
});

if (!args.file) {
  console.error('ingest: --file <playwright-json> is required');
  process.exit(2);
}
if (!fs.existsSync(args.file)) {
  // A skipped/cancelled Playwright run may not emit a file. Treat as a no-op
  // rather than failing the CI step.
  console.warn(`ingest: result file "${args.file}" not found — nothing to ingest`);
  process.exit(0);
}

const report = JSON.parse(fs.readFileSync(args.file, 'utf-8'));
const runTs = Math.floor(Date.now() / 1000);
const commitShort = String(args.commit).slice(0, 12);
const runId = args['run-id'] ?? `${runTs}-${commitShort}`;

const rows = extractRows(report, {
  run_id: runId,
  run_ts: runTs,
  commit: args.commit,
  branch: args.branch,
});

const ledgerPath = args.ledger;
fs.mkdirSync(path.dirname(ledgerPath), { recursive: true });

// Idempotency: skip if this run_id is already recorded.
const existing = readLedger(fs, ledgerPath);
if (existing.some((r) => r.run_id === runId)) {
  console.log(`ingest: run_id "${runId}" already present — skipping (${existing.length} rows on file)`);
  process.exit(0);
}

let merged = existing.concat(rows);
if (args.prune) merged = pruneOld(merged, runTs);

// Rewrite atomically (prune may drop old rows); otherwise this is an append.
const tmp = `${ledgerPath}.tmp`;
fs.writeFileSync(tmp, merged.map((r) => JSON.stringify(r)).join('\n') + (merged.length ? '\n' : ''));
fs.renameSync(tmp, ledgerPath);

const flaky = rows.filter((r) => r.is_flaky).length;
const smoke = rows.filter((r) => r.is_smoke).length;
console.log(
  `ingest: run ${runId} — +${rows.length} results (flaky: ${flaky}, smoke: ${smoke}); ledger now ${merged.length} rows`,
);
