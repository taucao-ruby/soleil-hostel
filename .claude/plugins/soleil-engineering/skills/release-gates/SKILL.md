---
description: Run Soleil Hostel release-readiness gates in the correct order.
disable-model-invocation: true
allowed-tools: Bash Read Grep Glob
---

# Release gates

Manually invoked. Wraps `scripts/ship.sh` — does not reimplement gates.

## Inspect first

Before running anything, confirm the canonical runner and any local changes:

1. `Glob scripts/*.sh` — confirm `scripts/ship.sh` exists. If absent, stop and report.
2. `git status --short` — list dirty paths so you know what is being gated.
3. `Read scripts/ship.sh` — re-read the current gate order; do not assume from memory.

## Run gates

Run the canonical runner unchanged:

```
bash scripts/ship.sh
```

This executes, in order:
1. `cd backend && php artisan test --stop-on-failure`
2. `cd frontend && npx tsc --noEmit`
3. `cd frontend && npx vitest run`
4. `docker compose --env-file .env.example -f docker-compose.yml config -q`

If `scripts/ship.sh` cannot be invoked (missing PHP, missing `node_modules`, missing Docker), run the gates individually using the same commands and report which environment piece is missing. Do NOT skip a gate silently.

## MCP self-test (only if applicable)

If `mcp/soleil-mcp/dist/index.js` exists, additionally run:

```
cd mcp/soleil-mcp && npm run self-test
```

Skip with a one-line note if `dist/` is absent — do not auto-build inside this skill.

## Reporting rules

- Quote the exact command and its exit status for each gate.
- For failures, capture the first failing line from the output and the file path.
- Do NOT claim "READY TO SHIP" unless `scripts/ship.sh` printed it.
- If you ran gates individually instead of via `scripts/ship.sh`, say so explicitly.
- Out-of-scope failures (issues not introduced by current branch) go to `docs/FINDINGS_BACKLOG.md` per repo policy — do not patch inline.

## Hard rules

- Do not bypass hooks (`--no-verify`, `--no-gpg-sign`).
- Do not modify `phpunit.xml`, `vitest.config.*`, or `docker-compose*` to make a gate pass.
- Do not install dependencies. If `vendor/` or `node_modules/` is missing, report it; let the user decide.
