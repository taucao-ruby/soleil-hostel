// @ts-check
/**
 * Shared logic for the Playwright flake-tracking pipeline (D-2).
 *
 * Zero runtime dependencies — pure Node ESM. History lives in an append-only
 * NDJSON ledger (one JSON object per test-result per line) that is committed to
 * the repo, so the 14-day rolling window survives indefinitely (no cache TTL).
 *
 * @smoke membership is DERIVED from the test/describe titles (the repo tags
 * smoke flows with the string "@smoke" and CI selects them with `--grep @smoke`)
 * — there is no separate registry to drift out of sync.
 */

export const WINDOW_DAYS = 14; // promotion / primary reporting window
export const DEMOTION_WINDOW_DAYS = 7; // faster demotion window
export const FLAKE_THRESHOLD = 0.02; // < 2% over 14d => smoke-eligible
export const WATCH_THRESHOLD = 0.05; // [2%,5%) => watch
export const DEMOTION_THRESHOLD = 0.05; // >= 5% over 7d => demote a @smoke test
export const MIN_RUNS_PROMOTE = 10; // below this over 14d => insufficient_data
export const MIN_RUNS_STALE = 3; // a @smoke test with < this over 14d is stale
export const DAY_SECONDS = 86_400;

/** @typedef {{ status: string, duration?: number, retry?: number }} PwResult */

/**
 * Collapse a test's retry results into one per-run outcome.
 * A run is FLAKY when it both failed/timedOut and passed (retry recovery) —
 * Playwright's own "flaky" signal. A consistent failure is a bug, not a flake.
 * @param {PwResult[]} results
 */
export function deriveResult(results) {
  const statuses = (results ?? []).map((r) => r.status);
  const retriesUsed = Math.max(0, statuses.length - 1);
  const durationMs = (results ?? []).reduce((s, r) => s + (r.duration ?? 0), 0);
  const hasFailed = statuses.some((s) => s === 'failed' || s === 'timedOut');
  const hasPassed = statuses.some((s) => s === 'passed');

  if (hasFailed && hasPassed) return { status: 'flaky', isFlaky: 1, retriesUsed, durationMs };
  if (hasPassed) return { status: 'passed', isFlaky: 0, retriesUsed, durationMs };
  if (statuses.length > 0 && statuses.every((s) => s === 'skipped'))
    return { status: 'skipped', isFlaky: 0, retriesUsed, durationMs };
  const timedOut = statuses.some((s) => s === 'timedOut');
  return { status: timedOut ? 'timedOut' : 'failed', isFlaky: 0, retriesUsed, durationMs };
}

/** Flow group = spec-file stem (e.g. flows/guest-booking.spec.ts -> "guest-booking"). */
export function deriveFlow(file) {
  const parts = String(file).replace(/\\/g, '/').split('/');
  const stem = (parts[parts.length - 1] ?? '').replace(/\.spec\.(ts|js)$/, '');
  return stem || 'unknown';
}

/** Stable, OS-independent file key: keep from "tests/e2e/" onward when present. */
export function normFile(file) {
  const u = String(file).replace(/\\/g, '/');
  const i = u.indexOf('tests/e2e/');
  return i >= 0 ? u.slice(i) : u;
}

/** @smoke is detected in any ancestor/spec title, or in Playwright's tags array. */
export function titleHasSmoke(titles, tags = []) {
  const inTitle = (titles ?? []).some((t) => /@smoke\b/i.test(String(t)));
  const inTags = (tags ?? []).some((t) => {
    const v = String(t).toLowerCase();
    return v === '@smoke' || v === 'smoke';
  });
  return inTitle || inTags;
}

/**
 * Walk the Playwright JSON `suites` tree into flat ledger rows.
 * @param {any} report parsed Playwright JSON
 * @param {{ run_id: string, run_ts: number, commit: string, branch: string }} meta
 */
export function extractRows(report, meta) {
  /** @type {any[]} */
  const rows = [];
  const walk = (suites, titlePath, file) => {
    for (const suite of suites ?? []) {
      const sTitle = suite.title ?? '';
      const sFile = suite.file ?? file ?? '';
      const nextPath = sTitle ? [...titlePath, sTitle] : titlePath;
      for (const spec of suite.specs ?? []) {
        const specFile = normFile(spec.file ?? sFile);
        const specPath = spec.title ? [...nextPath, spec.title] : nextPath;
        const isSmoke = titleHasSmoke(specPath, spec.tags);
        for (const test of spec.tests ?? []) {
          const d = deriveResult(test.results ?? []);
          const isFlaky = d.isFlaky || (test.status === 'flaky' ? 1 : 0);
          rows.push({
            run_id: meta.run_id,
            run_ts: meta.run_ts,
            commit: meta.commit,
            branch: meta.branch,
            test_id: `${specFile}::${spec.title}`,
            flow: deriveFlow(specFile),
            file: specFile,
            status: isFlaky ? 'flaky' : d.status,
            is_flaky: isFlaky,
            retries_used: d.retriesUsed,
            is_smoke: isSmoke ? 1 : 0,
            duration_ms: d.durationMs,
          });
        }
      }
      if (suite.suites) walk(suite.suites, nextPath, sFile);
    }
  };
  walk(report.suites ?? [], [], '');
  return rows;
}

/** Parse an NDJSON ledger file into row objects (tolerant of blank lines). */
export function readLedger(fs, ledgerPath) {
  if (!fs.existsSync(ledgerPath)) return [];
  const text = fs.readFileSync(ledgerPath, 'utf-8');
  /** @type {any[]} */
  const rows = [];
  for (const line of text.split('\n')) {
    const t = line.trim();
    if (!t) continue;
    try {
      rows.push(JSON.parse(t));
    } catch {
      // Skip a corrupt line rather than fail the whole pipeline.
    }
  }
  return rows;
}

/** Aggregate rows (already window-filtered) into per-test stats. */
function aggregateByTest(rows) {
  const m = new Map();
  for (const r of rows) {
    let e = m.get(r.test_id);
    if (!e) {
      e = { test_id: r.test_id, flow: r.flow, file: r.file, total_runs: 0, flaky_runs: 0, is_smoke: 0, last_seen_ts: 0 };
      m.set(r.test_id, e);
    }
    e.total_runs += 1;
    e.flaky_runs += r.is_flaky ? 1 : 0;
    e.is_smoke = e.is_smoke || (r.is_smoke ? 1 : 0);
    e.last_seen_ts = Math.max(e.last_seen_ts, r.run_ts);
  }
  return m;
}

function rate(flaky, total) {
  return total > 0 ? Math.round((flaky / total) * 10_000) / 10_000 : 0;
}

export function smokeStatus(flakeRate, totalRuns) {
  if (totalRuns < MIN_RUNS_PROMOTE) return 'insufficient_data';
  if (flakeRate < FLAKE_THRESHOLD) return 'smoke_eligible';
  if (flakeRate < WATCH_THRESHOLD) return 'watch';
  return 'unstable';
}

/**
 * Build the full report object from ledger rows.
 * Computes BOTH the 14-day window (promotion/reporting) and the 7-day window
 * (faster demotion) per test, so the gate can apply each rule on its own window.
 * @param {any[]} allRows
 * @param {number} nowTs epoch seconds
 */
export function buildReport(allRows, nowTs = Math.floor(Date.now() / 1000)) {
  const since14 = nowTs - WINDOW_DAYS * DAY_SECONDS;
  const since7 = nowTs - DEMOTION_WINDOW_DAYS * DAY_SECONDS;
  const rows14 = allRows.filter((r) => r.run_ts >= since14);
  const rows7 = allRows.filter((r) => r.run_ts >= since7);

  const by14 = aggregateByTest(rows14);
  const by7 = aggregateByTest(rows7);

  const tests = [...by14.values()]
    .map((e) => {
      const flakeRate14 = rate(e.flaky_runs, e.total_runs);
      const w7 = by7.get(e.test_id);
      const flakeRate7 = w7 ? rate(w7.flaky_runs, w7.total_runs) : 0;
      return {
        test_id: e.test_id,
        flow: e.flow,
        file: e.file,
        is_smoke: e.is_smoke === 1,
        total_runs: e.total_runs,
        flaky_runs: e.flaky_runs,
        flake_rate: flakeRate14,
        total_runs_7d: w7 ? w7.total_runs : 0,
        flake_rate_7d: flakeRate7,
        last_seen_ts: e.last_seen_ts,
        smoke_status: smokeStatus(flakeRate14, e.total_runs),
      };
    })
    .sort((a, b) => b.flake_rate - a.flake_rate || b.flaky_runs - a.flaky_runs);

  // Per-flow aggregate over the 14-day window.
  const flowMap = new Map();
  for (const r of rows14) {
    let f = flowMap.get(r.flow);
    if (!f) {
      f = { flow_group: r.flow, test_ids: new Set(), total_runs: 0, total_flaky_runs: 0 };
      flowMap.set(r.flow, f);
    }
    f.test_ids.add(r.test_id);
    f.total_runs += 1;
    f.total_flaky_runs += r.is_flaky ? 1 : 0;
  }
  const flows = [...flowMap.values()]
    .map((f) => {
      const flowRate = rate(f.total_flaky_runs, f.total_runs);
      return {
        flow_group: f.flow_group,
        test_count: f.test_ids.size,
        total_runs: f.total_runs,
        total_flaky_runs: f.total_flaky_runs,
        flow_flake_rate: flowRate,
        smoke_status: smokeStatus(flowRate, f.total_runs),
      };
    })
    .sort((a, b) => b.flow_flake_rate - a.flow_flake_rate);

  return {
    generated_at: new Date(nowTs * 1000).toISOString(),
    window_days: WINDOW_DAYS,
    demotion_window_days: DEMOTION_WINDOW_DAYS,
    flake_threshold: FLAKE_THRESHOLD,
    smoke_demotion_threshold: DEMOTION_THRESHOLD,
    min_runs_promote: MIN_RUNS_PROMOTE,
    summary: {
      total_tests: tests.length,
      smoke_eligible: tests.filter((t) => t.smoke_status === 'smoke_eligible').length,
      watch: tests.filter((t) => t.smoke_status === 'watch').length,
      unstable: tests.filter((t) => t.smoke_status === 'unstable').length,
      insufficient_data: tests.filter((t) => t.smoke_status === 'insufficient_data').length,
      currently_smoke: tests.filter((t) => t.is_smoke).length,
    },
    flows,
    tests,
  };
}

/** Prune rows older than `windowDays` (keep a small grace margin). */
export function pruneOld(rows, nowTs = Math.floor(Date.now() / 1000), windowDays = WINDOW_DAYS) {
  const cutoff = nowTs - (windowDays + 1) * DAY_SECONDS;
  return rows.filter((r) => r.run_ts >= cutoff);
}
