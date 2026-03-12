# Contributing — Toolchain Guide

Quick reference for package managers, config files, and hygiene guardrails.

## Package Manager

pnpm is the **only** supported package manager for `frontend/`.

```bash
corepack enable               # one-time setup
cd frontend && pnpm install   # installs from pnpm-lock.yaml
```

The required version is pinned in `frontend/package.json` via the `packageManager` field.
Never run `npm install` or `yarn` in `frontend/` — it creates a lockfile conflict
that CI will reject (check H-02).

## Environment Files

| File | Purpose | Safe to commit? |
|------|---------|:---:|
| `*.env.example`, `*.env.sample` | Templates with placeholder values | Yes |
| `.env`, `.env.local`, `.env.production` | Real credentials | **Never** |

If you need actual values, copy from the team vault or ask the team lead.
Regenerate your local `.env` from `.env.example`:

```bash
cp .env.example .env        # root (Docker Compose)
cp backend/.env.example backend/.env
```

## Canonical Config Files

| Tool | Canonical file | Do NOT create |
|------|---------------|---------------|
| ESLint | `frontend/eslint.config.js` (flat config) | `.eslintrc.*` variants |
| Vite | `frontend/vite.config.ts` | `vite.config.js`, `vite.config.d.ts` |
| TypeScript | `frontend/tsconfig.app.json` + `tsconfig.node.json` | — |

Legacy config files were removed in PR-2 and blocked by CI (check H-04).

## Forbidden Committed Artifacts

These file types must never be tracked in git:

- `*.sqlite`, `*.sqlite3`, `*_test.sqlite*` — test databases
- `*.log` — runtime log files
- `*.tsbuildinfo` — TypeScript incremental build cache
- `test_output.txt`, `test_results.txt`, `composer_output.txt` — command output dumps
- `run-logs/` — dev server output
- Non-template `.env.*` files — secrets

If you accidentally commit one:

```bash
git rm --cached <file>
# then add the pattern to .gitignore if not already there
```

## Running Hygiene Checks Locally

The hygiene script checks for all the issues listed above:

```bash
sh scripts/check-hygiene.sh
```

This runs automatically as part of the pre-commit hook (installed via `pnpm install`
at the repo root, which triggers `husky`). To skip in emergencies:

```bash
SKIP_HOOKS=1 git commit -m "..."
```

CI runs the same script as a hard gate — skipping the hook locally does not bypass CI.

## CI Checks

| Workflow | File | Checks |
|----------|------|--------|
| Tests, Lint & Security | `.github/workflows/tests.yml` | Backend tests, frontend lint/typecheck/vitest |
| Repo Hygiene | `.github/workflows/hygiene.yml` | H-01..H-05 (artifacts, lockfile, configs, .env) |
| Deploy | `.github/workflows/deploy.yml` | Production deployment |
