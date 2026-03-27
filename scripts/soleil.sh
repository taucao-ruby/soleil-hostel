#!/usr/bin/env bash
# soleil.sh — Wrapper for soleil-engine-cli in soleil-hostel consumer repo.
#
# Automatically injects --repo soleil-hostel for commands that require it
# (context, query, impact, cypher), unless caller already passed --repo.
# For analyze, defaults to repo root + --skills.
#
# Usage:
#   ./scripts/soleil.sh <command> [args...]
#   ./scripts/soleil.sh context AdminBookingController
#   ./scripts/soleil.sh context AdminBookingController --content
#   ./scripts/soleil.sh query "booking overlap prevention"
#   ./scripts/soleil.sh impact BookingService
#   ./scripts/soleil.sh analyze
#   ./scripts/soleil.sh --repo other-repo context SomeClass  # caller overrides

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
BIN="$REPO_ROOT/node_modules/.bin/soleil"

if [[ ! -x "$BIN" ]]; then
  echo "ERROR: soleil binary not found at $BIN" >&2
  echo "Run: npm install soleil-engine-cli" >&2
  exit 1
fi

COMMAND="${1:-}"

# Commands that accept --repo flag
case "$COMMAND" in
  context|query|impact|cypher)
    # Only inject --repo if caller has not already provided it
    has_repo=0
    for arg in "$@"; do
      [[ "$arg" == "--repo" || "$arg" == "-r" ]] && has_repo=1 && break
    done
    if [[ $has_repo -eq 0 ]]; then
      "$BIN" "$@" --repo soleil-hostel
    else
      "$BIN" "$@"
    fi
    ;;
  analyze)
    # Default to repo root + --skills; pass-through any extra flags from caller
    shift
    "$BIN" analyze "$REPO_ROOT" --skills "$@"
    ;;
  *)
    "$BIN" "$@"
    ;;
esac
