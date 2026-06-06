#!/usr/bin/env bash
# ci/gates/hygiene.sh — repo hygiene checks H-01..H-05.
# Mirrors hygiene.yml (which runs the very same scripts/check-hygiene.sh).
# No args; env-driven; idempotent. stdout = GATE_ line only.
set -euo pipefail

ID="hygiene"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

if sh scripts/check-hygiene.sh 1>&2; then
  echo "GATE_PASS $ID"
  exit 0
fi
echo "GATE_FAIL $ID check-hygiene"
exit 1
