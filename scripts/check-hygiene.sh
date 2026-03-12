#!/usr/bin/env sh
# scripts/check-hygiene.sh
# PR-5: Governance Hardening — SOLEIL HOSTEL
# Called by: CI (.github/workflows/hygiene.yml) and .husky/pre-commit
# Checks: H-01 through H-05
#
# Exit 0 = all checks pass.  Exit 1 = one or more checks failed.
# All failures are collected before exiting so the contributor sees every issue at once.

FAILED=0

# ---------------------------------------------------------------------------
# H-01 — Forbidden tracked artifacts
# Catches: SQLite DBs, log files, tsbuildinfo, test/build output dumps, run-logs
# ---------------------------------------------------------------------------
H01_HITS=$(git ls-files \
  | grep -v '^backend/vendor/' \
  | grep -v '^frontend/node_modules/' \
  | grep -E '(\.sqlite3?$|\.sqlite[-_](shm|wal)$|_test\.sqlite|\.log$|\.tsbuildinfo$)' \
  || true)

H01_DUMPS=$(git ls-files \
  | grep -E '(^|/)test_output\.txt$|(^|/)test_results\.txt$|(^|/)composer_output\.txt$|(^|/)run-logs/' \
  || true)

H01_ALL=""
if [ -n "$H01_HITS" ]; then H01_ALL="$H01_HITS"; fi
if [ -n "$H01_DUMPS" ]; then
  if [ -n "$H01_ALL" ]; then
    H01_ALL="${H01_ALL}
${H01_DUMPS}"
  else
    H01_ALL="$H01_DUMPS"
  fi
fi

if [ -n "$H01_ALL" ]; then
  echo "ERROR [H-01]: Forbidden artifact(s) tracked in git:"
  echo "$H01_ALL" | sed 's/^/  /'
  echo "Fix: git rm --cached <file>  (then add the pattern to .gitignore)"
  echo ""
  FAILED=1
else
  echo "ok [H-01] No forbidden artifacts tracked."
fi

# ---------------------------------------------------------------------------
# H-02 — Dual lockfile detection
# Ensures package-lock.json / yarn.lock do not coexist with pnpm-lock.yaml
# ---------------------------------------------------------------------------
H02_BAD=""
if git ls-files -- frontend/package-lock.json | grep -q .; then
  H02_BAD="frontend/package-lock.json"
fi
if git ls-files -- frontend/yarn.lock | grep -q .; then
  H02_BAD="${H02_BAD:+$H02_BAD, }frontend/yarn.lock"
fi

if [ -n "$H02_BAD" ]; then
  echo "ERROR [H-02]: Non-canonical lockfile(s) tracked: $H02_BAD"
  echo "  pnpm is the canonical package manager for frontend/."
  echo "Fix: git rm --cached <file>"
  echo ""
  FAILED=1
else
  echo "ok [H-02] No dual lockfile detected."
fi

# ---------------------------------------------------------------------------
# H-03 — Canonical package manager enforcement
# Verifies frontend/package.json has a pnpm packageManager field
# ---------------------------------------------------------------------------
if [ ! -f frontend/package.json ]; then
  echo "WARN [H-03]: frontend/package.json not found — skipping."
elif ! grep -q '"packageManager"' frontend/package.json; then
  echo "ERROR [H-03]: frontend/package.json missing \"packageManager\" field."
  echo "Fix: add '\"packageManager\": \"pnpm@<version>\"' to frontend/package.json"
  echo ""
  FAILED=1
elif grep '"packageManager"' frontend/package.json | grep -qE '"(npm|yarn)'; then
  echo "ERROR [H-03]: packageManager must specify pnpm, not npm or yarn."
  echo "Fix: set '\"packageManager\": \"pnpm@<version>\"' in frontend/package.json"
  echo ""
  FAILED=1
else
  echo "ok [H-03] packageManager field present and specifies pnpm."
fi

# ---------------------------------------------------------------------------
# H-04 — Dead config resurrection guard
# Blocks legacy ESLint / Vite config files from being re-committed
# ---------------------------------------------------------------------------
H04_FOUND=""
for cfg in \
  frontend/.eslintrc.cjs \
  frontend/.eslintrc.json \
  frontend/.eslintrc.js \
  frontend/.eslintrc.yml \
  frontend/.eslintrc.yaml \
  frontend/vite.config.js \
  frontend/vite.config.d.ts
do
  if git ls-files -- "$cfg" 2>/dev/null | grep -q .; then
    H04_FOUND="${H04_FOUND}  ${cfg}
"
  fi
done

if [ -n "$H04_FOUND" ]; then
  echo "ERROR [H-04]: Dead config file(s) tracked (removed in PR-2):"
  printf "%s" "$H04_FOUND"
  echo "  Canonical ESLint: eslint.config.js  |  Canonical Vite: vite.config.ts"
  echo "Fix: git rm --cached <file>"
  echo ""
  FAILED=1
else
  echo "ok [H-04] No dead config files tracked."
fi

# ---------------------------------------------------------------------------
# H-05 — .env variant files guard
# Blocks any tracked .env.* file that is NOT a .example / .sample / .template
# ---------------------------------------------------------------------------
TRACKED_ENV=$(git ls-files | grep '\.env\.' | grep -vE '\.(example|sample|template)$' || true)

if [ -n "$TRACKED_ENV" ]; then
  echo "ERROR [H-05]: Non-template .env variant file(s) tracked:"
  echo "$TRACKED_ENV" | sed 's/^/  /'
  echo "Fix: git rm --cached <file>  (real secrets must never be committed)"
  echo ""
  FAILED=1
else
  echo "ok [H-05] No non-template .env files tracked."
fi

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
echo ""
if [ "$FAILED" = "1" ]; then
  echo "FAILED: One or more hygiene checks did not pass."
  exit 1
else
  echo "All hygiene checks passed."
  exit 0
fi
