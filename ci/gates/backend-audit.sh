#!/usr/bin/env bash
# ci/gates/backend-audit.sh — Composer dependency security audit.
# Mirrors tests.yml:composer-audit (audit --locked). No args; idempotent.
# Needs network to reach Packagist; a transport flake is reported distinctly so
# it is not mistaken for a real advisory (re-run).
set -euo pipefail

ID="backend-audit"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT/backend"

if ! command -v composer >/dev/null 2>&1; then
  echo "GATE_FAIL $ID composer-missing"
  exit 1
fi

set +e
composer audit --locked --no-interaction --format=plain 1>&2
status=$?
set -e

if [ "$status" -eq 0 ]; then
  echo "GATE_PASS $ID"
  exit 0
fi
# Composer uses exit 100 for transport/runtime failures (e.g. Packagist timeout).
if [ "$status" -eq 100 ]; then
  echo "GATE_FAIL $ID transport-flake(exit100,re-run)"
  exit 1
fi
echo "GATE_FAIL $ID composer-advisory-or-abandoned"
exit 1
