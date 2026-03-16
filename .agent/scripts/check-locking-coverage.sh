#!/usr/bin/env bash
# check-locking-coverage.sh
# Verifies that known booking write paths contain lockForUpdate() or withLock().
# Exit: 0 = PASS, 1 = FAIL, 2 = UNKNOWN (missing evidence)
#
# Read-only. Never modifies files. CI-safe. Git Bash compatible.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SERVICES="$REPO_ROOT/backend/app/Services"

PASS=0
FAIL=1
UNKNOWN=2

fail=0
unknown=0

check_file_for_lock() {
  local label="$1"
  local file="$2"

  if [ ! -f "$file" ]; then
    echo "UNKNOWN [$label] File not found: $file"
    unknown=1
    return
  fi

  # Accept either withLock() (scope that delegates to lockForUpdate) or lockForUpdate() directly
  if grep -qE "(lockForUpdate|withLock)\(\)" "$file"; then
    echo "PASS    [$label] Lock pattern found in: $(basename "$file")"
  else
    echo "FAIL    [$label] No lockForUpdate() or withLock() found in: $file"
    fail=1
  fi
}

echo "=== Locking Coverage Check ==="
echo ""

# CreateBookingService — uses ->withLock() (scope delegating to lockForUpdate)
check_file_for_lock "booking-create" "$SERVICES/CreateBookingService.php"

# CancellationService — uses ->lockForUpdate() at lines 118, 318
check_file_for_lock "booking-cancel" "$SERVICES/CancellationService.php"

echo ""

if [ $unknown -eq 1 ]; then
  echo "RESULT: UNKNOWN — one or more source files were missing; cannot verify coverage"
  exit $UNKNOWN
fi

if [ $fail -eq 1 ]; then
  echo "RESULT: FAIL — one or more booking write paths are missing lock protection"
  exit $FAIL
fi

echo "RESULT: PASS — all checked booking write paths contain lock protection"
exit $PASS
