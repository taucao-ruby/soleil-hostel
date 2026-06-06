# E2E Flake Tracking (D-2)

Makes the policy **"a flow earns `@smoke` once its 14-day flake rate stays under 2%"**
(stated in `.github/workflows/e2e.yml` and `frontend/tests/e2e/README.md`) **measurable
and CI-enforceable**.

Design is deliberately lightweight for the current suite size (≈5 flows): zero new
runtime dependencies, pure Node ESM scripts, and an append-only NDJSON ledger committed
to the repo (no SQLite, no GitHub-cache TTL window problem).

## 1. What is flake rate

**Flake** — a test that is non-deterministic on the *same code*: it fails then passes.
A test that fails consistently is a **bug**, not a flake.

**Signal** — Playwright runs with `retries: 2` on CI ([playwright.config.ts](../frontend/playwright.config.ts)).
A run is counted **flaky** when its retry results contain both a `failed`/`timedOut`
and a `passed` entry (retry recovery) — equivalently, Playwright's own `flaky` status.

**Formula (rolling 14 days):**

```
flake_rate = flaky_runs / total_runs        (over the last 14 calendar days)
```

The 14-day window smooths day-to-day noise while still reacting within a sprint. A
faster **7-day** window drives demotion so a newly-unstable smoke flow is caught quickly.

| smoke_status        | Meaning                                   |
| ------------------- | ----------------------------------------- |
| `insufficient_data` | `< 10` runs in 14d — not enough to judge  |
| `smoke_eligible`    | `flake_rate < 2%` with `≥ 10` runs        |
| `watch`             | `2% ≤ flake_rate < 5%`                     |
| `unstable`          | `flake_rate ≥ 5%`                          |

## 2. `@smoke` source of truth

`@smoke` membership is **derived from the test/describe titles** — the repo tags smoke
flows with the literal string `@smoke` and CI selects them with `--grep @smoke`. There is
**no separate registry file** to drift out of sync (we deliberately avoided the
two-sources-of-truth class of bug, cf. `FINDINGS_BACKLOG` F-71). The pipeline reads
`@smoke` straight out of the Playwright JSON.

## 3. Promotion process (manual, gate-assisted)

A test becomes a **promotion candidate** when **all** hold:

1. `flake_rate < 2%` over 14 days, and
2. `total_runs ≥ 10` over 14 days, and
3. the spec file has **not** been modified in the last 3 days (git).

The gate **prints** candidates — it never auto-promotes. To promote: open a PR adding
`@smoke` to the test title / `describe` block (so `--grep @smoke` picks it up). That's it —
the next report recomputes membership from the tag.

## 4. Demotion process (CI-enforced)

`scripts/flake/gate.mjs` **exits 1** (fails CI) when a currently-`@smoke` test has:

- `flake_rate ≥ 5%` over the **7-day** window (requires `≥ 3` runs in that window so a
  one-sample window can't false-fail).

It additionally **warns** (does not fail) when a `@smoke` test has `< 3` runs in 14 days
(stopped running, or still bootstrapping history). To demote: remove the `@smoke` tag
from the test title / `describe` in a PR.

## 5. How to run locally

```bash
cd frontend
# 1. produce a Playwright JSON result (json reporter is already in playwright.config.ts)
pnpm exec playwright test --reporter=json > test-results/results.json   # or run normally

# 2. ingest that run into the ledger
pnpm flake:ingest --file test-results/results.json --commit "$(git rev-parse HEAD)" --branch "$(git branch --show-current)"

# 3. build the report (flake-report.json + flake-report.html) and run the gate
pnpm flake:full
```

`flake:full` = `flake:report` + `flake:gate`. Open `flake-report.html` in a browser for
the per-flow / per-test dashboard.

## 6. Ledger location & lifecycle

- **Ledger:** `frontend/tests/e2e/flake-history.ndjson` — append-only, one JSON object
  per test-result per line, **committed to the repo**. Durable across any number of CI
  runs (no cache TTL).
- **Persistence in CI:** the nightly `full` e2e job ingests the run, prunes rows older
  than ~14 days (`ingest --prune`), and commits the updated ledger with `[skip ci]` —
  the same pattern the frontend coverage-ratchet job already uses on `dev`.
- **Idempotency:** ingest is keyed by `run_id`; re-ingesting the same run is a no-op.
- **Generated, not committed:** `flake-report.json` / `flake-report.html` (gitignored);
  uploaded as CI artifacts per run.

## 7. Flow group mapping

A **flow group** is the spec-file stem: `tests/e2e/flows/guest-booking.spec.ts` →
`guest-booking`. This is computed in `scripts/flake/lib.mjs` (`deriveFlow`). The per-flow
table aggregates flake rate across every test in that file. If finer grouping is ever
needed, change `deriveFlow` (e.g. to read an explicit tag) — it is the single mapping point.

## Files

| Path                                   | Role                                                        |
| -------------------------------------- | ----------------------------------------------------------- |
| `frontend/scripts/flake/lib.mjs`       | Shared parse/aggregate/threshold logic (no deps)            |
| `frontend/scripts/flake/ingest.mjs`    | Playwright JSON → NDJSON ledger (idempotent, prunable)      |
| `frontend/scripts/flake/report.mjs`    | Ledger → `flake-report.json` + HTML dashboard               |
| `frontend/scripts/flake/gate.mjs`      | Demotion gate (exit 1) + promotion-candidate advisory       |
| `frontend/tests/e2e/flake-history.ndjson` | Committed rolling-window history ledger                  |
