#!/usr/bin/env bash
# =============================================================================
# check-locking-coverage.sh  —  Booking write-path locking guard  (v2.0.0)
#
# Verifies that every service which performs BOOKING MUTATIONS holds a
# pessimistic lock (lockForUpdate() / withLock()). This protects the domain
# invariant in .agent/rules/booking-integrity.md and CLAUDE.md:
# "booking-critical writes keep pessimistic locking".
#
# Requires bash (set -o pipefail, local). Read-only: never modifies source.
# Dependencies: coreutils + grep + awk only. Git Bash and Ubuntu compatible.
# -----------------------------------------------------------------------------
# T-6 KEEP-vs-RETIRE AUDIT  (permanent record — see decision memo for evidence)
#
#   1. FALSE POSITIVE RATE ....... KEEP  — the narrow pattern (lockForUpdate|
#        withLock)\( matches only real lock primitives; no evidence of flagging
#        correctly-locked code. Method-level proof is delegated to the test
#        suite to keep false positives at zero.
#   2. COVERAGE COMPLETENESS ..... FIX   — the previous v1 hardcoded only
#        CreateBookingService + CancellationService and was already STALE:
#        BookingService (confirm/markPaid/cancel/softDelete/restore) is a third
#        locked booking write path it never checked.
#   3. MAINTENANCE BURDEN ........ FIX   — v1's service list was hardcoded.
#        Per the ticket's own rule, hardcoded => auto-discovery is the fix, not
#        retirement. This rewrite removes ALL hardcoded names.
#   4. LAST CORRECT FAILURE ...... VALIDATE — v1 was never wired into CI, so it
#        had never blocked a deploy. This version is CI-gated and was validated
#        against a deliberately-unlocked fixture before integration.
#
#   FINAL DECISION: KEEP, re-mechanised.
#
#   ADAPTATION NOTE: T-6 specified Kubernetes (`kubectl get deployments -l
#   booking-eng/write-path=true`) + Java annotations (@WithLock /
#   @Transactional(lock=PESSIMISTIC_WRITE)). This repository is a Laravel
#   monolith with NO Kubernetes and NO microservices; locking is the PHP call
#   ->lockForUpdate() / the Eloquent withLock() scope, not an annotation.
#   Discovery is therefore driven by a checked-in manifest
#   (booking-write-services.yaml) — the declarative, fail-closed analogue of
#   "the label must be present on every Deployment". Running kubectl here would
#   return zero services on every run and wedge CI red forever.
# -----------------------------------------------------------------------------
# DELEGATED LOCKING: some booking write entry points (e.g. the Stripe webhook
# handler) hold no in-file lock and instead route the mutation through another
# enforced service. Such an entry declares `lockedVia: Service::method` in the
# manifest and PASSES only if (1) its file actually calls `method(` AND (2) the
# delegate Service is itself in-file locked and present in the discovered set.
# This keeps the false-positive rate at zero while still guarding the chain.
# -----------------------------------------------------------------------------
# EXIT CODE CONTRACT (consumed by .github/workflows/locking-guard.yml):
#   0 = PASS    — every discovered write path has verified lock protection.
#   1 = FAIL    — one or more unlocked write paths detected (block deploy).
#   2 = INFRA   — discovery timeout, missing manifest, or a declared service
#                 file is missing (manifest drift). Must page on-call.
#   3 = EMPTY   — discovery returned zero services. Fail closed: treat as
#                 misconfiguration (label/manifest wiped). Must page on-call.
#
# USAGE:
#   check-locking-coverage.sh [--auto-discover]   Full guard run (CI default).
#   check-locking-coverage.sh --list-services     Print discovered services, exit 0.
#   check-locking-coverage.sh --help              Show this help.
#
# ENV OVERRIDES:
#   LOCKING_GUARD_MANIFEST   Path to the manifest (default: <repo>/booking-write-services.yaml)
#   LOCKING_GUARD_REPORT     Path to the JSON report (default: <repo>/locking-report.json)
# =============================================================================

set -euo pipefail

VERSION="2.0.0"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SCRIPT_PATH="$SCRIPT_DIR/$(basename "$0")"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

MANIFEST="${LOCKING_GUARD_MANIFEST:-$REPO_ROOT/booking-write-services.yaml}"
REPORT_PATH="${LOCKING_GUARD_REPORT:-$REPO_ROOT/locking-report.json}"

# A lock primitive is lockForUpdate(...) or withLock(...) (the Eloquent scope
# that delegates to lockForUpdate). Open paren allows optional whitespace/args.
LOCK_PATTERN='(lockForUpdate|withLock)[[:space:]]*\('

DISCOVERY_TIMEOUT_SECS=30

EXIT_PASS=0
EXIT_UNLOCKED=1
EXIT_INFRA=2
EXIT_EMPTY=3

log() { printf '%s\n' "$*" >&2; }

usage() {
  sed -n '2,60p' "$SCRIPT_PATH" | sed 's/^# \{0,1\}//'
}

# JSON-escape a controlled scalar (backslash + double-quote only).
json_escape() {
  printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'
}

# -----------------------------------------------------------------------------
# DISCOVERY — single source of truth. Emits TSV "name<TAB>file<TAB>writePaths"
# for every declared booking write service. NO hardcoded service names.
# Returns 2 (INFRA) if the manifest is absent.
# -----------------------------------------------------------------------------
discover_write_services() {
  if [ ! -f "$MANIFEST" ]; then
    log "INFRA: discovery manifest not found: $MANIFEST"
    log "INFRA: cannot enumerate booking write services. See LOCKING-GUARD.md."
    return "$EXIT_INFRA"
  fi

  # Parse the constrained flow-map subset (see manifest FORMAT CONTRACT).
  # Each value is cut at the first ',' or '}', then trimmed. Order-independent.
  awk '
    /^[ \t]*#/ { next }
    index($0, "name:") && index($0, "file:") {
      name = $0; sub(/.*name:[ \t]*/,        "", name); sub(/[,}].*/, "", name); gsub(/^[ \t]+|[ \t]+$/, "", name)
      file = $0; sub(/.*file:[ \t]*/,        "", file); sub(/[,}].*/, "", file); gsub(/^[ \t]+|[ \t]+$/, "", file)
      wp   = $0; sub(/.*writePaths:[ \t]*/,  "", wp);   sub(/[,}].*/, "", wp);   gsub(/^[ \t]+|[ \t]+$/, "", wp)
      via  = $0; sub(/.*lockedVia:[ \t]*/,   "", via);  sub(/[,}].*/, "", via);  gsub(/^[ \t]+|[ \t]+$/, "", via)
      if (index($0, "writePaths:") == 0) wp = ""
      if (index($0, "lockedVia:")  == 0) via = ""
      if (name != "" && file != "") printf "%s\t%s\t%s\t%s\n", name, file, wp, via
    }
  ' "$MANIFEST"
}

# Run discovery under a hard timeout, in a separate process so a future
# network-backed discovery method genuinely cannot hang the guard.
discover_with_timeout() {
  local tsv rc
  if command -v timeout >/dev/null 2>&1; then
    set +e
    tsv="$(timeout "${DISCOVERY_TIMEOUT_SECS}s" "$SCRIPT_PATH" --emit-services)"
    rc=$?
    set -e
    if [ "$rc" -eq 124 ]; then
      log "DISCOVERY_TIMEOUT: discovery exceeded ${DISCOVERY_TIMEOUT_SECS}s"
      exit "$EXIT_INFRA"
    fi
    if [ "$rc" -ne 0 ]; then
      log "INFRA: discovery failed (exit $rc)"
      exit "$EXIT_INFRA"
    fi
  else
    # No timeout(1) available (rare). Run discovery inline; manifest read
    # cannot block, so this is acceptable and still deterministic.
    log "WARN: timeout(1) unavailable; running discovery without a watchdog."
    set +e
    tsv="$(discover_write_services)"
    rc=$?
    set -e
    if [ "$rc" -ne 0 ]; then
      log "INFRA: discovery failed (exit $rc)"
      exit "$EXIT_INFRA"
    fi
  fi
  printf '%s' "$tsv"
}

# Verify one declared service file contains a lock primitive.
#   0 = locked, 1 = unlocked, 2 = file missing (manifest drift / INFRA).
verify_file_lock() {
  local rel="$1"
  local abs="$REPO_ROOT/$rel"
  if [ ! -f "$abs" ]; then
    return "$EXIT_INFRA"
  fi
  if grep -Eq "$LOCK_PATTERN" "$abs"; then
    return "$EXIT_PASS"
  fi
  return "$EXIT_UNLOCKED"
}

run_guard() {
  log "=== Booking locking-coverage guard v${VERSION} ==="
  log "Manifest: $MANIFEST"

  local tsv
  tsv="$(discover_with_timeout)"

  # FAIL CLOSED: an empty discovery set is a misconfiguration, never a pass.
  if ! printf '%s' "$tsv" | grep -q '[^[:space:]]'; then
    log "FATAL: discovery returned zero booking write services."
    log "FATAL: This is a configuration error, not a pass. Failing closed."
    log "FATAL: Check that booking-write-services.yaml still declares services."
    exit "$EXIT_EMPTY"
  fi

  # Self-documenting CI log: echo the discovered set up front.
  log "Discovered booking write services:"
  printf '%s\n' "$tsv" | awk -F'\t' 'NF{print "  - " $1 "  (" $2 ")"}' >&2
  log ""

  local had_unlocked=0
  local had_infra=0

  # ---- Pass 1: read every manifest row and compute IN-FILE lock status, keyed
  # by service name. Two passes are needed so a delegated entry (lockedVia) can
  # be resolved against its target regardless of manifest order.
  local -a S_NAME=() S_FILE=() S_WP=() S_VIA=()
  local -A INFILE=()
  local idx=0
  while IFS=$'\t' read -r name file writePaths lockedVia; do
    [ -n "$name" ] || continue
    S_NAME[$idx]="$name"; S_FILE[$idx]="$file"; S_WP[$idx]="$writePaths"; S_VIA[$idx]="$lockedVia"
    set +e
    verify_file_lock "$file"
    local vrc=$?
    set -e
    case "$vrc" in
      "$EXIT_PASS")  INFILE[$name]="yes" ;;
      "$EXIT_INFRA") INFILE[$name]="missing" ;;
      *)             INFILE[$name]="no" ;;
    esac
    idx=$((idx + 1))
  done <<EOF
$tsv
EOF

  # ---- Pass 2: resolve final lock status (in-file OR verified delegation) and
  # emit results. Collect ALL failures — never short-circuit on first failure.
  local json_services="" sep=""
  local i
  for (( i = 0; i < idx; i++ )); do
    local name="${S_NAME[$i]}" file="${S_FILE[$i]}" writePaths="${S_WP[$i]}" via="${S_VIA[$i]}"
    local infile="${INFILE[$name]}"
    local locked_bool="false" reason=""

    if [ "$infile" = "missing" ]; then
      had_infra=1
      reason="file_missing"
      log "INFRA: declared file missing for service '$name': $file"
    elif [ "$infile" = "yes" ]; then
      locked_bool="true"
      reason="in_file_lock"
    elif [ -n "$via" ]; then
      # Delegated locking: the write path holds no in-file lock but routes its
      # booking mutation through "DelegateService::delegateMethod". Verified iff
      # (1) this file actually calls delegateMethod(  — so the delegation is
      # real, not just declared — AND (2) DelegateService is itself in-file
      # locked. Either failing => UNLOCKED (delegation contract broken).
      local via_service="${via%%::*}"
      local via_method=""
      case "$via" in *::*) via_method="${via##*::}" ;; esac
      local calls="no"
      if [ -n "$via_method" ] && grep -Eq "${via_method}[[:space:]]*\(" "$REPO_ROOT/$file" 2>/dev/null; then
        calls="yes"
      fi
      local delegate="${INFILE[$via_service]:-absent}"
      if [ "$calls" = "yes" ] && [ "$delegate" = "yes" ]; then
        locked_bool="true"
        reason="locked_via:${via}"
      else
        had_unlocked=1
        reason="delegation_unverified(calls=${calls},delegate=${delegate})"
      fi
    else
      had_unlocked=1
      reason="no_lock"
    fi

    log "  [$name] locked=${locked_bool} (${reason})"

    # Per-write-path structured lines on stdout (stable grep contract for CI).
    local label="LOCKED_PATH"
    [ "$locked_bool" = "true" ] || label="UNLOCKED_PATH"
    if [ -n "${writePaths// /}" ]; then
      for wp in $writePaths; do
        printf '%s %s %s\n' "$label" "$name" "$wp"
      done
    else
      printf '%s %s %s\n' "$label" "$name" "(service)"
    fi

    # JSON object: required name/writePaths/locked, plus optional lockedVia.
    local wp_json="" wp_sep=""
    if [ -n "${writePaths// /}" ]; then
      for wp in $writePaths; do
        wp_json="${wp_json}${wp_sep}\"$(json_escape "$wp")\""
        wp_sep=", "
      done
    fi
    local via_json=""
    [ -n "$via" ] && via_json=", \"lockedVia\": \"$(json_escape "$via")\""
    json_services="${json_services}${sep}{\"name\": \"$(json_escape "$name")\", \"writePaths\": [${wp_json}], \"locked\": ${locked_bool}${via_json}}"
    sep=", "
  done

  local passed_bool="true"
  if [ "$had_unlocked" -eq 1 ] || [ "$had_infra" -eq 1 ]; then
    passed_bool="false"
  fi

  # Emit the JSON report to a file (CI artifact) AND to stdout.
  local report="{\"passed\": ${passed_bool}, \"services\": [${json_services}]}"
  printf '%s\n' "$report" > "$REPORT_PATH"
  printf '%s\n' "$report"

  log ""
  log "Report written to: $REPORT_PATH"

  # Exit-code precedence: INFRA (2) over UNLOCKED (1) over PASS (0).
  if [ "$had_infra" -eq 1 ]; then
    log "RESULT: INFRA — a declared booking write service file is missing (manifest drift)."
    exit "$EXIT_INFRA"
  fi
  if [ "$had_unlocked" -eq 1 ]; then
    log "RESULT: FAIL — one or more booking write paths are missing lock protection."
    exit "$EXIT_UNLOCKED"
  fi
  log "RESULT: PASS — all discovered booking write paths contain lock protection."
  exit "$EXIT_PASS"
}

# -----------------------------------------------------------------------------
# Entry point
# -----------------------------------------------------------------------------
case "${1:-}" in
  --emit-services)
    # Internal data mode: raw TSV to stdout, used by discover_with_timeout.
    discover_write_services
    ;;
  --list-services)
    tsv="$(discover_write_services)"
    log "Discovered booking write services:"
    printf '%s\n' "$tsv" | awk -F'\t' 'NF{print "  - " $1}' >&2
    printf '%s\n' "$tsv" | awk -F'\t' 'NF{print $1}'
    exit "$EXIT_PASS"
    ;;
  --auto-discover|"")
    run_guard
    ;;
  -h|--help)
    usage
    exit "$EXIT_PASS"
    ;;
  *)
    log "Unknown argument: $1"
    log "Run with --help for usage."
    exit "$EXIT_INFRA"
    ;;
esac
