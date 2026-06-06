#!/usr/bin/env node
// @ts-check
/**
 * CI gate for the @smoke flake rule.
 *
 *  - DEMOTION (exit 1): a currently-@smoke test whose 7-day flake_rate ≥ 5%
 *    (requires ≥ 3 runs in the 7d window so a 1-sample window can't false-fail).
 *  - STALE (warn): a currently-@smoke test with < 3 runs in 14d (stopped running
 *    or still bootstrapping) — surfaced, not failed, so a fresh ledger is usable.
 *  - PROMOTION (advisory): a non-@smoke test that is smoke_eligible (< 2% over
 *    14d, ≥ 10 runs) AND untouched by git for ≥ 3 days. Printed only — promotion
 *    stays a human decision (add the @smoke tag in a PR).
 *
 * Usage:
 *   node scripts/flake/gate.mjs --report flake-report.json [--no-git]
 */
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { parseArgs } from 'node:util';
import { DEMOTION_THRESHOLD, MIN_RUNS_STALE } from './lib.mjs';

const MODIFIED_DAYS = 3;

const { values: args } = parseArgs({
  options: {
    report: { type: 'string', default: 'flake-report.json' },
    'no-git': { type: 'boolean', default: false },
  },
});

if (!fs.existsSync(args.report)) {
  console.error(`gate: report "${args.report}" not found — run flake:report first`);
  process.exit(2);
}
const report = JSON.parse(fs.readFileSync(args.report, 'utf-8'));
const pct = (x) => `${(x * 100).toFixed(1)}%`;

let exitCode = 0;

// ---- Demotion (hard fail) + stale (warn) for currently-@smoke tests ----
for (const t of report.tests) {
  if (!t.is_smoke) continue;
  if (t.total_runs_7d >= MIN_RUNS_STALE && t.flake_rate_7d >= DEMOTION_THRESHOLD) {
    console.error(
      `DEMOTION REQUIRED: @smoke test "${t.test_id}" — 7d flake_rate=${pct(t.flake_rate_7d)} ` +
        `over ${t.total_runs_7d} runs (threshold ${pct(DEMOTION_THRESHOLD)}). Remove the @smoke tag.`,
    );
    exitCode = 1;
  }
  if (t.total_runs < MIN_RUNS_STALE) {
    console.warn(
      `STALE @smoke test "${t.test_id}" — only ${t.total_runs} runs in 14d. ` +
        `Verify it is still running (or it is still bootstrapping history).`,
    );
  }
}

// ---- Promotion candidates (advisory only) ----
const lastModifiedDays = (file) => {
  if (args['no-git']) return null;
  try {
    const ct = execFileSync('git', ['log', '-1', '--format=%ct', '--', file], { encoding: 'utf-8' }).trim();
    if (!ct) return null; // untracked / no history
    return (Math.floor(Date.now() / 1000) - Number(ct)) / 86_400;
  } catch {
    return null; // git unavailable — treat modified-check as unknown
  }
};

const candidates = [];
for (const t of report.tests) {
  if (t.is_smoke || t.smoke_status !== 'smoke_eligible') continue;
  const ageDays = lastModifiedDays(t.file);
  const recentlyModified = ageDays !== null && ageDays < MODIFIED_DAYS;
  if (recentlyModified) continue;
  candidates.push({ ...t, ageDays });
}

if (candidates.length > 0) {
  console.log(`\nPromotion candidates (flake_rate < 2%, ≥10 runs/14d, untouched ≥${MODIFIED_DAYS}d):`);
  for (const c of candidates) {
    const age = c.ageDays === null ? 'git n/a' : `${c.ageDays.toFixed(1)}d since edit`;
    console.log(`  PROMOTE? ${c.test_id} — flake_rate=${pct(c.flake_rate)}, runs=${c.total_runs}, ${age}`);
  }
} else {
  console.log('\nNo promotion candidates this run.');
}

console.log(
  `\ngate: ${report.summary.currently_smoke} @smoke · ${report.summary.unstable} unstable · ` +
    `${report.summary.smoke_eligible} eligible · exit ${exitCode}`,
);
process.exit(exitCode);
