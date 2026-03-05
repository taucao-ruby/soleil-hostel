# Git Hooks — Soleil Hostel

Local enforcement hooks for code quality and safety.

Source: `.husky/`, `tools/hooks/`, root `package.json`, `tools/hooks/hook-policy.json`.

<!-- merged from DEVELOPMENT_HOOKS.md 2026-03-05 -->

## Purpose

Git hooks in this repository provide fast local guardrails for:

- Secret/file hygiene at commit time
- Commit message consistency
- Pre-push verification aligned with CI gates

Hooks are designed to be:

- Safe by default
- Cross-platform (Windows Git Bash, WSL2, Linux, macOS)
- Bypassable intentionally when needed

## Hook Policy Source

- Policy file: `tools/hooks/hook-policy.json`
- Bypass env var: `SKIP_HOOKS`

Policy defines:

- Blocked paths and sensitive file globs
- Secret detection regex patterns
- Max staged file size limit (2MB)
- Verification targets and exact commands

## Hooks Installed

| Hook       | Shell Script        | Node.js Script               |
| ---------- | ------------------- | ---------------------------- |
| pre-commit | `.husky/pre-commit` | `tools/hooks/pre-commit.mjs` |
| commit-msg | `.husky/commit-msg` | `tools/hooks/commit-msg.mjs` |
| pre-push   | `.husky/pre-push`   | `tools/hooks/pre-push.mjs`   |

## Pre-commit: What It Runs

1. **Blocked files check**: `.env*` (except `.env.example`), private keys, `vendor/`, `node_modules/`, cache/storage internals
2. **Secret detection**: Scans added lines for common secret patterns
3. **File size check**: Blocks staged files > 2 MB
4. **Binary file check**: Blocks non-allowed binary files
5. **lint-staged** (if frontend files staged):
   - `eslint --fix` on JS/TS files
   - `prettier --write` on text assets

## Commit-msg: What It Validates

Conventional Commits format on first non-empty line:

- Types: `feat|fix|chore|docs|refactor|test|build|ci|perf|revert`
- Optional scopes: `backend|frontend|infra|docs`
- Optional breaking marker: `!`
- Auto-generated `Merge ...` and `Revert ...` messages are allowed

Examples:

```
feat(frontend): add booking date guard
fix(backend)!: tighten token revocation checks
docs: update operational notes
```

## Pre-push: What It Runs

Detects changed files relative to upstream (`@{u}`, fallback to `origin/dev` then `origin/main`):

| Changed area        | Gate commands run                                                   |
| ------------------- | ------------------------------------------------------------------- |
| Backend files       | `cd backend && php artisan test`                                    |
| Frontend files      | `cd frontend && npx tsc --noEmit` + `cd frontend && npx vitest run` |
| Compose/infra files | `docker compose config` (optional if Docker CLI unavailable)        |

If diff base cannot be resolved: runs full baseline (all three).

Dry-run preview:

```bash
node tools/hooks/pre-push.mjs --dry-run
```

## Installation

```bash
npm install
npm run hooks:install
```

This configures Git to use Husky v9 (`core.hooksPath=.husky/_`).

Verify:

```bash
git config core.hooksPath
# Expected: .husky/_
```

## Bypass Policy

**When allowed**: only when risk is understood and intentional.

### One-off bypass

```bash
git commit --no-verify
git push --no-verify
```

### Environment variable bypass

```bash
# Linux/macOS/WSL2
SKIP_HOOKS=1 git commit -m "chore: ..."

# PowerShell
$env:SKIP_HOOKS=1; git commit -m "chore: ..."
```

**Required**: document reason in commit message + notify team lead.

**Prohibited**: bypassing on `main`/production branches.

## Troubleshooting Windows/WSL

| Issue                             | Solution                                                                                            |
| --------------------------------- | --------------------------------------------------------------------------------------------------- |
| Hooks do not run                  | Check `git config core.hooksPath` — expected `.husky/_`. Run `npm run hooks:install`.               |
| `node` not found in Git Bash      | Ensure Node is on PATH in Git Bash. Restart terminal after Node install.                            |
| CRLF issues                       | Hooks use Node.js (cross-platform). Check `.gitattributes` for line ending config.                  |
| `docker compose config` fails     | Docker target is optional — only runs when compose files changed. Install Docker Desktop if needed. |
| False positive secret detection   | Secret patterns are conservative. Use `--no-verify` or `SKIP_HOOKS=1` for known-safe cases.         |
| Permission denied on hook scripts | On Unix: `chmod +x .husky/pre-commit .husky/commit-msg .husky/pre-push`                             |
