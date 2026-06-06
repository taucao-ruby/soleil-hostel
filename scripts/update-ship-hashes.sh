#!/usr/bin/env bash
# scripts/update-ship-hashes.sh — regenerate the ship gate tamper seal (T-5).
# =============================================================================
# Rewrites .ship-hashes.sha256 to cover scripts/ship.sh, ci/gates/manifest.tsv
# and every ci/gates/*.sh. ship.sh verifies these before running any gate, so
# regenerating them declares the new gate scripts TRUSTED — a deliberate,
# security-reviewed action. Hence the required --confirm flag.
#
# Idempotent: deterministic ordering => running twice yields an identical file.
# No sudo, no installs, no eval.
# =============================================================================
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"
HASHFILE=".ship-hashes.sha256"

if [ "${1:-}" != "--confirm" ]; then
  cat >&2 <<EOF
Refusing to regenerate ${HASHFILE} without --confirm.

These hashes are the tamper seal for the ship CI-mirror gate; regenerating them
tells ship.sh the new gate scripts are trusted. Make it deliberate.

  Usage:  sh scripts/update-ship-hashes.sh --confirm
  Commit: chore: update ship gate hashes [security-review-required]
EOF
  exit 1
fi

# Deterministic, repo-relative, canonical (two-space) sha256 lines.
{
  sha256sum scripts/ship.sh
  sha256sum ci/gates/manifest.tsv
  find ci/gates -name '*.sh' -type f | LC_ALL=C sort | xargs sha256sum
} | sed 's/ \*/  /' > "$HASHFILE"

echo "Wrote ${HASHFILE}:" >&2
cat "$HASHFILE" >&2
echo "" >&2
echo "Verify: sha256sum -c ${HASHFILE}" >&2
echo "Commit: git add ${HASHFILE} scripts/ship.sh ci/gates && git commit -m 'chore: update ship gate hashes [security-review-required]'" >&2
