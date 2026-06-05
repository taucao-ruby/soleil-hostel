#!/usr/bin/env bash
# ci/gates/backend-static.sh — backend static analysis & code style.
# Mirrors tests.yml jobs: lint (php -l), pint, phpstan, psalm. Runs ALL four and
# reports every failure (CI runs them as independent jobs). No args; idempotent.
set -euo pipefail

ID="backend-static"
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

fail=0

# 1. PHP syntax scan (mirrors tests.yml:lint)
if find app tests -name '*.php' -print0 | xargs -0 -P 4 -I {} php -l {} 1>&2; then
  printf '  php -l: OK\n' >&2
else
  printf '  php -l: FAIL\n' >&2
  fail=1
fi

# 2. Laravel Pint code style (mirrors tests.yml:pint)
if php -d opcache.enable_cli=0 vendor/bin/pint --test 1>&2; then
  printf '  pint:   OK\n' >&2
else
  printf '  pint:   FAIL (run: vendor/bin/pint)\n' >&2
  fail=1
fi

# 3. PHPStan Level 5 (mirrors tests.yml:phpstan; lockfile-pinned analyzer)
if php -d opcache.enable_cli=0 vendor/bin/phpstan analyse --no-progress 1>&2; then
  printf '  phpstan: OK\n' >&2
else
  printf '  phpstan: FAIL\n' >&2
  fail=1
fi

# 4. Psalm (mirrors tests.yml:psalm; JIT disabled as in CI)
if php -d opcache.enable_cli=0 -d opcache.jit=0 -d opcache.jit_buffer_size=0 \
    vendor/bin/psalm --no-progress 1>&2; then
  printf '  psalm:  OK\n' >&2
else
  printf '  psalm:  FAIL\n' >&2
  fail=1
fi

if [ "$fail" -eq 0 ]; then
  echo "GATE_PASS $ID"
  exit 0
fi
echo "GATE_FAIL $ID static-analysis-failure"
exit 1
