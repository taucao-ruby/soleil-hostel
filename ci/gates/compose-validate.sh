#!/usr/bin/env bash
# ci/gates/compose-validate.sh — docker compose config validation.
# Mirrors tests.yml:docker-compose-validate. No args; env-driven; idempotent.
# Validates structure only (`config -q`) — never prints interpolated values.
set -euo pipefail

ID="compose-validate"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

if ! command -v docker >/dev/null 2>&1; then
  echo "GATE_FAIL $ID docker-missing"
  exit 1
fi

ENV_FILE="${COMPOSE_ENV_FILE:-$REPO_ROOT/.env.example}"
COMPOSE_FILE="${COMPOSE_FILE:-$REPO_ROOT/docker-compose.yml}"

# Host env has precedence over --env-file; avoid empty values shadowing the
# template (mirrors the CI job's `unset REDIS_PASSWORD`).
unset REDIS_PASSWORD 2>/dev/null || true

if docker compose \
    --project-directory "$REPO_ROOT" \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    config -q 1>&2; then
  echo "GATE_PASS $ID"
  exit 0
fi
echo "GATE_FAIL $ID compose-config-invalid"
exit 1
