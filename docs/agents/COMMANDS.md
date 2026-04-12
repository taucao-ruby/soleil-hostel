# Commands Reference — Soleil Hostel

## Slash Commands (Claude Code CLI)

| Command | Use case | Input |
|---------|----------|-------|
| `/audit-security` | OWASP + business integrity audit | Optional focus area |
| `/fix-backend` | Fix backend issue with invariant enforcement | Task description |
| `/fix-frontend` | Fix frontend issue with TS strict enforcement | Task description |
| `/review-pr` | Architecture + invariant + test coverage review | PR number or branch |
| `/sync-docs` | Compare docs against codebase reality | Optional focus area |
| `/ship` | Release-safety gate runner | — |

Heavy commands (`audit-security`, `review-pr`, `sync-docs`, `ship`) require explicit user invocation and will confirm scope before executing.

## Quality Gates (must pass before merge)

> Full gate reference with CI job map: `docs/COMMANDS_AND_GATES.md`

```bash
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

## MCP Verify Targets

| Target | Command |
|--------|---------|
| `backend_tests` | `cd backend && php artisan test` |
| `frontend_typecheck` | `cd frontend && npx tsc --noEmit` |
| `frontend_unit_tests` | `cd frontend && npx vitest run` |
| `docker_compose_config` | `docker compose config` |
| `backend_lint` | `cd backend && vendor/bin/pint --test` |
| `frontend_lint` | `cd frontend && pnpm lint` |

## Additional Checks (non-blocking)

```bash
cd backend && vendor/bin/phpstan analyse      # Level 5
cd backend && vendor/bin/psalm                # Level 1
cd backend && vendor/bin/pint --test
cd frontend && pnpm lint
cd frontend && pnpm format
cd backend && composer audit
```

## Prerequisites

| Tool | Purpose | Install |
|------|---------|---------|
| `jq` | Claude Code hook JSON parsing | https://jqlang.github.io/jq/download/ |
| `node` >= 20 | Frontend tools, MCP server | https://nodejs.org/ |
| `php` >= 8.2 | Backend runtime | https://www.php.net/ |
| `docker` | Container orchestration | https://www.docker.com/ |

> **`jq` is required for Claude Code hooks to function.** Without it, all PreToolUse/PostToolUse hooks degrade to fail-open mode. Run `bash scripts/verify-control-plane.sh` to check.

## Setup Commands

```bash
cd backend && composer install
cd backend && cp .env.example .env
cd backend && php artisan key:generate
cd backend && php artisan migrate:fresh --seed
cd frontend && pnpm install
npm install && npm run hooks:install
cd mcp/soleil-mcp && npm install && npm run build
```

## Dev Servers

```bash
npm run dev                                                    # Both
cd backend && php artisan serve --host=127.0.0.1 --port=8000   # Backend
cd frontend && pnpm dev                                        # Frontend → :5173
docker compose up --build                                      # Docker stack
```

## Artisan Commands (custom)

| Command | Purpose | Notes |
|---------|---------|-------|
| `php artisan stays:backfill-operational` | Create `expected` Stay rows for confirmed bookings with `check_out >= today` that pre-date lazy stay creation | Safe to re-run (idempotent via `firstOrCreate`) |
| `php artisan stays:backfill-operational --dry-run` | Count eligible bookings without persisting | Prints summary only |
| `php artisan cache:warmup` | Pre-populate room availability cache | — |
| `php artisan bookings:prune-soft-deleted` | Purge old soft-deleted booking records | — |
| `php artisan ai:eval --all-phases` | Run full AI harness regression gate (all phases, all datasets) | Nightly CI at 03:00; blocks deploy on failure |
| `php artisan ai:eval --phase=N` | Run regression for a specific phase (2, 2plus, 3) | Use `--dataset=faq_lookup\|room_discovery\|admin_draft` to filter |

Selection criteria for `stays:backfill-operational`: `status = 'confirmed'` AND `check_out >= today` AND no existing stay row.
Does NOT touch cancelled, refund_pending, refunded, refund_failed, or past-checkout bookings. Source: `app/Console/Commands/BackfillOperationalStays.php`.
Canonical operational note and source-of-truth boundaries: `docs/DOMAIN_LAYERS.md`.
