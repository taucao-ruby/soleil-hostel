#!/usr/bin/env bash
# check-migration-safety.sh
# Checks recently modified migration files for common safety violations:
#   1. Missing or empty down() method          — mechanically reliable PASS/FAIL
#   2. PG-only features without driver guard   — mechanically reliable PASS/FAIL
#   3. Auto-generated index/constraint names   — ADVISORY ONLY (WARN, not FAIL)
#      Detection is partial: catches zero-argument ->index()/>unique()/>primary() calls only.
#      Single-column string calls and ->foreign() without explicit names are NOT detected.
#      Manual naming review is always required for new migrations.
#
# Exit: 0 = PASS (or PASS with warnings), 1 = FAIL, 2 = UNKNOWN (missing evidence)
#
# Read-only. Never modifies files. CI-safe. Git Bash compatible.
# Pass a migration file path as $1, or omit to check all migrations modified in the last git commit.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
MIGRATIONS_DIR="$REPO_ROOT/backend/database/migrations"

PASS=0
FAIL=1
UNKNOWN=2

fail=0
unknown=0
warn=0

# Determine files to check
if [ $# -ge 1 ]; then
  FILES=("$1")
else
  # Check migration files changed in the last commit
  mapfile -t FILES < <(git -C "$REPO_ROOT" diff --name-only HEAD~1 HEAD -- "backend/database/migrations/*.php" 2>/dev/null | sed "s|^|$REPO_ROOT/|" || true)
  if [ ${#FILES[@]} -eq 0 ]; then
    echo "UNKNOWN — No migration files found in last commit diff. Pass a file path as argument to check a specific file."
    exit $UNKNOWN
  fi
fi

echo "=== Migration Safety Check ==="
echo "Checking ${#FILES[@]} file(s)"
echo ""

for file in "${FILES[@]}"; do
  if [ ! -f "$file" ]; then
    echo "UNKNOWN [$(basename "$file")] File not found: $file"
    unknown=1
    continue
  fi

  label="$(basename "$file")"
  file_fail=0

  # 1. Check for down() method
  if ! grep -q "function down" "$file"; then
    echo "FAIL    [$label] Missing down() method"
    file_fail=1
    fail=1
  else
    # Check that down() body is not empty or comment-only
    # Extract content between 'function down' and the next closing brace at the method level
    # Simple heuristic: check if down() contains only whitespace/comments
    down_body=$(awk '/function down/,/^\s*\}/' "$file" | tail -n +2 | head -n -1)
    if echo "$down_body" | grep -qvE '^\s*(//.*)?$'; then
      echo "PASS    [$label] down() has implementation"
    else
      echo "FAIL    [$label] down() body appears empty or comment-only"
      file_fail=1
      fail=1
    fi
  fi

  # 2. Check for PG-only features without guard
  pg_features=("EXCLUDE USING" "daterange(" "btree_gist" "USING gist")
  pg_feature_found=0
  for feature in "${pg_features[@]}"; do
    if grep -q "$feature" "$file"; then
      pg_feature_found=1
      break
    fi
  done

  if [ $pg_feature_found -eq 1 ]; then
    if grep -q "getDriverName" "$file"; then
      echo "PASS    [$label] PG-only feature has DB::getDriverName() guard"
    else
      echo "FAIL    [$label] PG-only feature used without DB::getDriverName() === 'pgsql' guard"
      file_fail=1
      fail=1
    fi
  fi

  # 3. Check for auto-generated names on constraints/indexes
  # Patterns that accept a name parameter explicitly: ->unique('name'), ->index('name'), ->foreign('col', 'name')
  # Flag calls to ->unique(), ->index(), ->primary(), ->foreign() with no name argument (single or zero args after column)
  # Heuristic: constraint-creating calls with empty second argument position
  if grep -qE "\->(unique|index|primary)\(\s*\)" "$file"; then
    echo "WARN    [$label] Zero-argument ->unique()/->index()/->primary() detected — likely auto-generated name; verify explicit naming"
    echo "         (Advisory only: single-column and ->foreign() auto-names are not detected by this check)"
    warn=$((warn + 1))
  fi

  if [ $file_fail -eq 0 ]; then
    echo "PASS    [$label] All checks passed"
  fi

  echo ""
done

if [ $unknown -eq 1 ]; then
  echo "RESULT: UNKNOWN — one or more files could not be checked"
  exit $UNKNOWN
fi

if [ $fail -eq 1 ]; then
  echo "RESULT: FAIL — one or more migration safety violations found"
  exit $FAIL
fi

if [ $warn -gt 0 ]; then
  echo "RESULT: PASS with $warn advisory warning(s) — see WARN lines above; manual index/constraint naming review required"
else
  echo "RESULT: PASS — no hard violations detected (naming convention check is advisory; verify explicit names in any new migration)"
fi
exit $PASS
