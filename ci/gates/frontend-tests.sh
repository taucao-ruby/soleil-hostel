#!/usr/bin/env bash
# ci/gates/frontend-tests.sh — frontend unit tests WITH coverage thresholds.
# Mirrors tests.yml:frontend-unit-tests (pnpm test:unit --coverage). Vitest
# enforces the floors in frontend/coverage-thresholds.json at config load.
# (ship.sh previously ran `vitest run` with NO coverage — strictly weaker.)
set -euo pipefail

ID="frontend-tests"
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

if pnpm test:unit --coverage 1>&2; then
  echo "GATE_PASS $ID"
  exit 0
fi
echo "GATE_FAIL $ID vitest-or-coverage-threshold"
exit 1
