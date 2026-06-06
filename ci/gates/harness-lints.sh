#!/usr/bin/env bash
# ci/gates/harness-lints.sh — Claude Code instruction-surface governance lints.
# Mirrors harness-lints.yml (lint-memory / audit-skills / lint-doc-pointers).
# No args; env-driven; idempotent. Runs all three and reports every failure.
set -euo pipefail

ID="harness-lints"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

if ! command -v node >/dev/null 2>&1; then
  echo "GATE_FAIL $ID node-missing(install:node 20)"
  exit 1
fi

fail=0
for lint in lint-memory.mjs audit-skills.mjs lint-doc-pointers.mjs; do
  f="scripts/$lint"
  [ -f "$f" ] || { printf '  %s: SKIP (absent)\n' "$lint" >&2; continue; }
  if node "$f" 1>&2; then
    printf '  %s: OK\n' "$lint" >&2
  else
    printf '  %s: FAIL\n' "$lint" >&2
    fail=1
  fi
done

if [ "$fail" -eq 0 ]; then
  echo "GATE_PASS $ID"
  exit 0
fi
echo "GATE_FAIL $ID harness-lint-failure"
exit 1
