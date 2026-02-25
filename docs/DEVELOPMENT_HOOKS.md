# Development Hooks

## Purpose

Git hooks in this repository provide fast local guardrails for:

- Secret/file hygiene at commit time
- Commit message consistency
- Pre-push verification aligned with CI gates

Hooks are designed to be:

- Safe by default
- Cross-platform (Windows Git Bash, WSL2, Linux, macOS)
- Bypassable intentionally when needed

## Project Baseline (Feb 11, 2026)

- Branch status snapshot: `dev` was recorded as 8 commits ahead of `main`
- Verified commands:
  - `cd backend && php artisan test` (`737 tests`, `2071 assertions`)
  - `cd frontend && npx tsc --noEmit`
  - `cd frontend && npx vitest run` (`194 tests`)
  - `docker compose config`
- Known non-blocking warnings:
  - PHPUnit doc-comment metadata deprecation warnings
  - Vitest `act(...)` + non-boolean DOM attribute warnings

Hooks fail on non-zero exit status, not warning text.

## Install / Enable

From repository root:

```bash
npm install
npm run hooks:install
```

This configures Git hooks via Husky (`core.hooksPath=.husky/_` on Husky v9).

## Hook Policy Source

- Policy file: `tools/hooks/hook-policy.json`
- Bypass env var: `SKIP_HOOKS`

Policy defines:

- Blocked paths and sensitive file globs
- Secret detection regex patterns
- Max staged file size limit (2MB)
- Verification targets and exact commands

## Hook Behavior

### pre-commit (fast)

Runs before commit and blocks when it finds:

- Blocked files/paths (`.env*`, private keys, vendor/node_modules, cache/storage internals)
- Potential secrets in added lines
- Oversized staged files (>2MB)
- Non-allowed binary files (binary dumps)

Then, if frontend files are staged, it runs `lint-staged`:

- `eslint --fix` on staged frontend JS/TS files
- `prettier --write` on staged frontend text assets

### commit-msg

Validates first non-empty commit message line against Conventional Commits:

- Types: `feat|fix|chore|docs|refactor|test|build|ci|perf|revert`
- Optional scopes: `backend|frontend|infra|docs`
- Optional breaking marker: `!`

Examples:

- `feat(frontend): add booking date guard`
- `fix(backend)!: tighten token revocation checks`
- `docs: update operational notes`

`Merge ...` and `Revert ...` auto-generated messages are allowed.

### pre-push (gates)

Detects changed files relative to upstream (`@{u}` fallback to `origin/dev` then `origin/main`) and runs:

- Backend changes: `cd backend && php artisan test`
- Frontend changes: `cd frontend && npx tsc --noEmit` + `cd frontend && npx vitest run`
- Compose/infrastructure changes: `docker compose config` (optional if Docker CLI unavailable)

If diff base cannot be resolved, it falls back to full baseline verification:

- backend tests + frontend typecheck + frontend unit tests

Dry-run preview:

- `node tools/hooks/pre-push.mjs --dry-run`

## Bypass (intentional only)

- One-off bypass:
  - `git commit --no-verify`
  - `git push --no-verify`
- Env bypass for all hooks in a command:
  - Linux/macOS/WSL2: `SKIP_HOOKS=1 git commit -m "chore: ..."`
  - PowerShell: `$env:SKIP_HOOKS=1; git commit -m "chore: ..."`

Use bypass only when risk is understood and intentional.

## Troubleshooting

### Hooks do not run

- Check hooks path:
  - `git config core.hooksPath`
  - Expected: `.husky/_`
- Reinstall hooks:
  - `npm run hooks:install`

### `node` not found in Git Bash (Windows)

- Ensure Node is installed and on `PATH` in Git Bash.
- Restart terminal after Node installation.

### `docker compose config` fails locally

- Docker target is optional and runs only when compose-related files changed.
- Install Docker Desktop / Docker CLI if you want that local gate.

### False positives in secret detection

- Secret patterns are intentionally conservative but not perfect.
- If intentional and safe, bypass once with `--no-verify` or `SKIP_HOOKS=1`.
