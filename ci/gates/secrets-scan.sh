#!/usr/bin/env bash
# ci/gates/secrets-scan.sh — Gitleaks full-history secret scan.
# Mirrors tests.yml:security-scan. No args; env-driven; idempotent.
# --redact guarantees no secret VALUE is ever written to logs (secret hygiene).
set -euo pipefail

ID="secrets-scan"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

if ! command -v gitleaks >/dev/null 2>&1; then
  echo "GATE_FAIL $ID gitleaks-missing(install:https://github.com/gitleaks/gitleaks/releases)"
  exit 1
fi

if gitleaks detect \
    --source . \
    --redact \
    --log-opts="--all" \
    --config .gitleaks.toml 1>&2; then
  echo "GATE_PASS $ID"
  exit 0
fi
echo "GATE_FAIL $ID exposed-credentials"
exit 1
