#!/usr/bin/env bash
# ci/gates/backend-tests.sh — backend test suite WITH the CI coverage floor.
# Mirrors tests.yml:backend-tests EXACTLY: --parallel + --min-coverage 95.
# (The previous ship.sh ran `artisan test --stop-on-failure` with NO coverage
# floor — strictly weaker than CI. This gate closes that gap.)
# Requires the PostgreSQL test DB (orchestrator only runs this when DB is up).
set -euo pipefail

ID="backend-tests"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT/backend"

if ! command -v php >/dev/null 2>&1; then
  echo "GATE_FAIL $ID php-missing(install:php 8.3)"
  exit 1
fi
if [ ! -d vendor ]; then
  echo "GATE_FAIL $ID vendor-missing(run:composer install)"
  exit 1
fi

# Same invocation as CI. Coverage needs a driver (xdebug/pcov); if absent,
# artisan emits a clear error and this gate fails — that is honest: the coverage
# gate genuinely did not run, so READY TO SHIP must not claim it did.
if php artisan test \
    --parallel \
    --processes=4 \
    --coverage \
    --min-coverage-percentage=95 1>&2; then
  echo "GATE_PASS $ID"
  exit 0
fi
echo "GATE_FAIL $ID tests-or-coverage-below-95"
exit 1
