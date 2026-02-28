#!/usr/bin/env bash
# scripts/ship.sh — Pre-release CI gate runner
#
# Runs the three mandatory quality gates in sequence.
# Prints "READY TO SHIP" on success; exits non-zero with the failing step label on failure.
#
# Usage:
#   bash scripts/ship.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

pass() { echo "[PASS] $1"; }
fail() { echo "[FAIL] $1"; exit 1; }

echo "=== Soleil Hostel — Ship Gate ==="
echo ""

# Gate 1: Backend tests
echo "--- Gate 1: php artisan test ---"
(cd "$REPO_ROOT/backend" && php artisan test --stop-on-failure) \
    && pass "Backend tests" \
    || fail "Backend tests"

echo ""

# Gate 2: Frontend type-check
echo "--- Gate 2: tsc --noEmit ---"
(cd "$REPO_ROOT/frontend" && npx tsc --noEmit) \
    && pass "TypeScript type-check" \
    || fail "TypeScript type-check"

echo ""

# Gate 3: Frontend unit tests
echo "--- Gate 3: vitest run ---"
(cd "$REPO_ROOT/frontend" && npx vitest run) \
    && pass "Frontend unit tests" \
    || fail "Frontend unit tests"

echo ""
echo "================================="
echo "✅  READY TO SHIP"
echo "================================="
