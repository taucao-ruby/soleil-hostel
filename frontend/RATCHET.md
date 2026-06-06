# Coverage Ratchet — Runbook

Coverage floors live in `frontend/coverage-thresholds.json` (lines / branches / functions /
statements). `vite.config.ts` reads them at load time via `readRatchetedThresholds()`, and Vitest
fails the run if any metric drops below its floor. The floor only ever moves **up**.

## How it works

- **Enforce:** every PR/push runs `pnpm test:unit --coverage`; Vitest enforces the stored floors.
- **Ratchet:** after a green run on `dev`, CI runs `scripts/ratchet-coverage.sh`, which raises each
  floor to `max(old, measured)` (truncated to 2 dp) and commits the file back with `[skip ci]`.
- **main:** never auto-written. The raised floor reaches `main` through the normal `dev→main` merge,
  preserving the human-reviewed-main invariant.

## Bootstrap from zero

1. Set every value in `coverage-thresholds.json` to `0` (or delete the file — the config falls back
   to all-zero floors and warns).
2. Push to `dev`. The first ratchet run records the real measured floors.

## Break-glass: lower a floor manually

You normally can't — the ratchet only raises. To intentionally lower one (e.g. you deleted a
well-tested feature): edit `coverage-thresholds.json` directly and open a **PR** (CODEOWNERS requires
review). Keep each value a number in `[0, 100]`, or the config resets all floors to `0`.

## Disable temporarily

Set the relevant metric(s) to `0` (a `0` floor always passes); set all four to `0` for a full bypass.
Re-raise by restoring the values and pushing to `dev`.

## Known limitations

- The state file is **not** cryptographically signed; CODEOWNERS review is the only tamper guard.
- The ratchet push assumes the bot can write to `dev`. If `dev` is protection-locked, add the bot (or
  `GITHUB_TOKEN`) to the branch-protection bypass list, or it can't commit the raised floor.
- Concurrent `dev` pushes can make the ratchet `git push` reject (non-fast-forward); just re-run CI.
