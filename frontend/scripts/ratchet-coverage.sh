#!/usr/bin/env bash
# scripts/ratchet-coverage.sh
#
# Move the coverage floors in coverage-thresholds.json UPWARD only — never down.
# The pass/fail gate itself is enforced by Vitest (it reads the same file); this
# script only records a new, higher floor after a green run so the gate tightens
# over time.
#
# Reads:  <frontend>/coverage/coverage-summary.json   (latest measured pct)
#         <frontend>/coverage-thresholds.json          (current floors)
# Writes: <frontend>/coverage-thresholds.json          (atomically: .tmp -> mv)
#
# Per metric: new_floor = max(old_floor, measured_pct), truncated to 2 decimals.
#
# Exit codes (the gate failure comes from Vitest, never from this script):
#   0  at least one floor increased  -> CI should commit the file
#   2  all floors unchanged          -> nothing to commit
#   3  prerequisite missing (jq / coverage summary) -> environment error
#   (never exits 1)
#
# Idempotent: a second run on the same coverage-summary.json changes nothing and
# returns 2.
#
# NOTE: the ticket says "POSIX sh", but it also invokes this with `bash` and asks
# for `set -o pipefail`, which is not POSIX. We use bash so strict mode is honest.
set -euo pipefail

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
FRONTEND_DIR=$(dirname -- "$SCRIPT_DIR")
SUMMARY="$FRONTEND_DIR/coverage/coverage-summary.json"
FLOORS="$FRONTEND_DIR/coverage-thresholds.json"
TMP="$FLOORS.tmp"

command -v jq >/dev/null 2>&1 || { echo "ratchet: jq is required but not installed" >&2; exit 3; }
[ -f "$SUMMARY" ] || { echo "ratchet: $SUMMARY not found — run coverage first" >&2; exit 3; }

# Bootstrap an all-zero floor file on the first ever run.
if [ ! -f "$FLOORS" ]; then
  printf '{\n  "lines": 0,\n  "branches": 0,\n  "functions": 0,\n  "statements": 0\n}\n' > "$FLOORS"
fi

# Compute the next floors + whether anything rose. Truncate to 2 dp with a tiny
# epsilon so float error (e.g. 71.33 * 100 = 7132.9999) can't shave off a cent.
CALC=$(jq -n \
  --slurpfile s "$SUMMARY" \
  --slurpfile f "$FLOORS" '
    def t2: (. * 100 + 1e-6 | floor) / 100;
    ($s[0].total) as $sum
    | ($f[0]) as $old
    | reduce ["lines","branches","functions","statements"][] as $k
        ({floors: {}, raised: false};
          ($sum[$k].pct | t2)        as $new
          | (($old[$k] // 0) | t2)   as $cur
          | (if $new > $cur then $new else $cur end) as $next
          | .floors[$k] = $next
          | .raised = (.raised or ($next > $cur)))
  ')

printf '%s\n' "$CALC" | jq '.floors' > "$TMP"
mv -f "$TMP" "$FLOORS"

if [ "$(printf '%s' "$CALC" | jq -r '.raised')" = "true" ]; then
  echo "ratchet: coverage floors raised:"
  cat "$FLOORS"
  exit 0
fi

echo "ratchet: coverage floors unchanged"
exit 2
