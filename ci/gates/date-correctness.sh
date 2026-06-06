#!/usr/bin/env bash
# ci/gates/date-correctness.sh — booking date-correctness guard.
# Mirrors tests.yml:date-correctness-guard. No args; env-driven; idempotent.
# Contract: emits exactly one `GATE_PASS <id>` / `GATE_FAIL <id> <reason>` to
# stdout; all diagnostics go to stderr; exit 0 pass / 1 fail.
set -euo pipefail

ID="date-correctness"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

if bash scripts/assert-date-correctness.sh 1>&2; then
  echo "GATE_PASS $ID"
  exit 0
fi
echo "GATE_FAIL $ID assert-date-correctness"
exit 1
