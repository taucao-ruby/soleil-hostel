#!/usr/bin/env bash
# soleil.sh — Wrapper for soleil-engine-cli in soleil-hostel consumer repo.
#
# Automatically appends --repo soleil-hostel to all commands that accept it
# (context, query, impact, cypher). For analyze, passes the repo root path.
#
# Usage:
#   ./scripts/soleil.sh <command> [args...]
#   ./scripts/soleil.sh context AdminBookingController
#   ./scripts/soleil.sh query "booking overlap prevention"
#   ./scripts/soleil.sh impact BookingService
#   ./scripts/soleil.sh analyze

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
BIN="$REPO_ROOT/node_modules/.bin/soleil"

if [[ ! -x "$BIN" ]]; then
  echo "ERROR: soleil binary not found at $BIN" >&2
  echo "Run: npm install --save-dev /path/to/soleil-engine-cli-*.tgz" >&2
  exit 1
fi

REPO_FLAG_COMMANDS="context query impact cypher"
COMMAND="${1:-}"

# Inject --repo for commands that need it
if echo "$REPO_FLAG_COMMANDS" | grep -qw "$COMMAND"; then
  "$BIN" "$@" --repo soleil-hostel
elif [[ "$COMMAND" == "analyze" ]]; then
  # analyze takes a path argument
  "$BIN" analyze "$REPO_ROOT" --skills
else
  "$BIN" "$@"
fi
