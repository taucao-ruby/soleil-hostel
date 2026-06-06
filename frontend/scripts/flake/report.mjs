#!/usr/bin/env node
// @ts-check
/**
 * Generate flake-report.json (+ optional HTML dashboard) from the NDJSON ledger.
 *
 * Usage:
 *   node scripts/flake/report.mjs \
 *     --ledger tests/e2e/flake-history.ndjson \
 *     --out flake-report.json [--html flake-report.html]
 */
import fs from 'node:fs';
import { parseArgs } from 'node:util';
import { readLedger, buildReport } from './lib.mjs';

const { values: args } = parseArgs({
  options: {
    ledger: { type: 'string', default: 'tests/e2e/flake-history.ndjson' },
    out: { type: 'string', default: 'flake-report.json' },
    html: { type: 'string' },
  },
});

const rows = readLedger(fs, args.ledger);
const report = buildReport(rows);

fs.writeFileSync(args.out, JSON.stringify(report, null, 2) + '\n');
console.log(`report: wrote ${args.out}`);
console.log(`  tests: ${report.summary.total_tests}`);
console.log(`  smoke_eligible: ${report.summary.smoke_eligible}`);
console.log(`  watch:          ${report.summary.watch}`);
console.log(`  unstable:       ${report.summary.unstable}`);
console.log(`  insufficient:   ${report.summary.insufficient_data}`);

if (args.html) {
  fs.writeFileSync(args.html, renderHtml(report));
  console.log(`report: wrote ${args.html}`);
}

function esc(s) {
  return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}
function pct(x) {
  return `${(x * 100).toFixed(1)}%`;
}
function badge(status) {
  const color =
    { smoke_eligible: '#1a7f37', watch: '#9a6700', unstable: '#cf222e', insufficient_data: '#57606a' }[status] ?? '#57606a';
  return `<span style="background:${color};color:#fff;border-radius:10px;padding:1px 8px;font-size:12px">${esc(
    status.replace(/_/g, ' '),
  )}</span>`;
}

function renderHtml(rep) {
  const flowRows = rep.flows
    .map(
      (f) =>
        `<tr><td>${esc(f.flow_group)}</td><td>${pct(f.flow_flake_rate)}</td><td>${f.test_count}</td><td>${
          f.total_runs
        }</td><td>${badge(f.smoke_status)}</td></tr>`,
    )
    .join('');
  const testRows = rep.tests
    .map(
      (t) =>
        `<tr><td>${t.is_smoke ? '🔶 ' : ''}${esc(t.test_id)}</td><td>${esc(t.flow)}</td><td>${pct(
          t.flake_rate,
        )}</td><td>${t.flaky_runs}/${t.total_runs}</td><td>${pct(t.flake_rate_7d)} (${t.total_runs_7d})</td><td>${badge(
          t.smoke_status,
        )}</td></tr>`,
    )
    .join('');
  return `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Flake trend — ${rep.window_days}d rolling</title>
<style>
  body{font-family:system-ui,Segoe UI,Arial,sans-serif;margin:24px;color:#1f2328}
  h1{font-size:20px} h2{font-size:15px;margin-top:28px}
  table{border-collapse:collapse;width:100%;margin-top:8px;font-size:13px}
  th,td{border:1px solid #d0d7de;padding:6px 10px;text-align:left}
  th{background:#f6f8fa} caption{text-align:left;color:#57606a;font-size:12px;margin-bottom:6px}
  .meta{color:#57606a;font-size:12px}
</style></head><body>
<h1>Flake trend — ${rep.window_days}-day rolling</h1>
<p class="meta">Generated ${esc(rep.generated_at)} · promotion &lt; ${pct(rep.flake_threshold)} · demotion ≥ ${pct(
    rep.smoke_demotion_threshold,
  )} (${rep.demotion_window_days}d) · min ${rep.min_runs_promote} runs</p>
<p class="meta">tests: ${rep.summary.total_tests} · 🔶 smoke: ${rep.summary.currently_smoke} · eligible: ${
    rep.summary.smoke_eligible
  } · watch: ${rep.summary.watch} · unstable: ${rep.summary.unstable} · insufficient: ${rep.summary.insufficient_data}</p>
<h2>By flow</h2>
<table><caption>Flake rate aggregated per flow group (14d).</caption>
<tr><th>Flow</th><th>Flake rate</th><th>Tests</th><th>Runs (14d)</th><th>Status</th></tr>${flowRows ||
    '<tr><td colspan="5">No data in window.</td></tr>'}</table>
<h2>By test</h2>
<table><caption>🔶 = currently tagged @smoke. 7d column drives demotion.</caption>
<tr><th>Test</th><th>Flow</th><th>Flake rate (14d)</th><th>Flaky/Runs</th><th>7d (runs)</th><th>Status</th></tr>${testRows ||
    '<tr><td colspan="6">No data in window.</td></tr>'}</table>
</body></html>
`;
}
