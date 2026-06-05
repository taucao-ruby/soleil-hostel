#!/usr/bin/env bash
# ci/gates/frontend-checks.sh — frontend typecheck + ESLint + production build.
# Mirrors tests.yml jobs: frontend-typecheck (tsc --noEmit) and frontend-lint
# (pnpm run build, then pnpm run lint). Runs all three; reports every failure.
# (ship.sh previously ran tsc ONLY — no ESLint, no build. This closes that gap.)
set -euo pipefail

ID="frontend-checks"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT/frontend"

if ! command -v pnpm >/dev/null 2>&1; then
  echo "GATE_FAIL $ID pnpm-missing(install:pnpm 9)"
  exit 1
fi
if [ ! -d node_modules ]; then
  echo "GATE_FAIL $ID node_modules-missing(run:pnpm install --frozen-lockfile)"
  exit 1
fi

fail=0

# 1. TypeScript typecheck (mirrors tests.yml:frontend-typecheck)
if npx tsc --noEmit 1>&2; then
  printf '  tsc:    OK\n' >&2
else
  printf '  tsc:    FAIL\n' >&2
  fail=1
fi

# 2. Production build (mirrors tests.yml:frontend-lint build step)
if VITE_API_URL="/api" NODE_ENV=production pnpm run build 1>&2; then
  printf '  build:  OK\n' >&2
else
  printf '  build:  FAIL\n' >&2
  fail=1
fi

# 3. ESLint (mirrors tests.yml:frontend-lint lint step)
if pnpm run lint 1>&2; then
  printf '  eslint: OK\n' >&2
else
  printf '  eslint: FAIL\n' >&2
  fail=1
fi

if [ "$fail" -eq 0 ]; then
  echo "GATE_PASS $ID"
  exit 0
fi
echo "GATE_FAIL $ID typecheck/build/lint-failure"
exit 1
