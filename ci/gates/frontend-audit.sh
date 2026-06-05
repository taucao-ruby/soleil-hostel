#!/usr/bin/env bash
# ci/gates/frontend-audit.sh — NPM dependency audit (HIGH/CRITICAL, blocking).
# Mirrors tests.yml:npm-audit, which runs scripts/audit-with-exceptions.mjs
# (allowlist in frontend/.audit-exceptions.json). No args; idempotent.
set -euo pipefail

ID="frontend-audit"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT/frontend"

if ! command -v node >/dev/null 2>&1; then
  echo "GATE_FAIL $ID node-missing(install:node 20)"
  exit 1
fi

if node ../scripts/audit-with-exceptions.mjs 1>&2; then
  echo "GATE_PASS $ID"
  exit 0
fi
echo "GATE_FAIL $ID npm-high-or-critical-advisory"
exit 1
