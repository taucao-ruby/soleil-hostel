#!/usr/bin/env bash
# scripts/ship.sh — Pre-release CI mirror & delta gate (T-5)
# =============================================================================
# "READY TO SHIP" must imply the SAME gates as CI — no weaker guarantee. This
# script is a hybrid CI mirror for a repo whose CI runs ~16 jobs directly on
# ubuntu-latest (NO container image to pull): it runs every CI gate it can run
# locally at full CI strength, and prints an EXPLICIT local-vs-CI delta for the
# gates it cannot, so the readiness claim is always honest.
#
# GATE INVENTORY — sourced from ci/gates/manifest.tsv (derived from
# .github/workflows/). DO NOT hardcode gates here; edit the manifest. ship.sh
# only ORCHESTRATES — every gate's logic lives in ci/gates/<id>.sh.
#
# MODES
#   ./scripts/ship.sh                 mirror mode (default) — run local gates
#   ./scripts/ship.sh --mode=mirror   same as above
#   ./scripts/ship.sh --mode=delta    diagnostic only — JSON env diff, runs NO gates
#   ./scripts/ship.sh --help
#
# READY TO SHIP gate: if HEAD's commit message contains "ready to ship"
# (case-insensitive), the strict certification flow is FORCED regardless of
# flags: delta precheck -> full local mirror -> verify CI is green for this SHA
# (gh) -> emit READY_TO_SHIP_VERIFIED. There is NO flag to skip those sub-checks.
#
# EXIT CODES
#   mirror : 0 = all runnable gates passed (banner states full vs partial mirror)
#            1 = a runnable gate failed, integrity/tamper, or READY-TO-SHIP block
#   delta  : 0 = compliant (no differences)
#            1 = non-compliant with blocking differences
#            2 = non-compliant with warnings only
#
# HARDENING: gate scripts + manifest + this file are SHA-256 pinned
# (.ship-hashes.sha256, verified before any gate runs). No sudo, no installs, no
# eval. Secret values are never logged (presence only).
# =============================================================================

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEFAULT_MANIFEST="$REPO_ROOT/ci/gates/manifest.tsv"
MANIFEST="${SHIP_MANIFEST:-$DEFAULT_MANIFEST}"
HASHFILE="${SHIP_HASHFILE:-$REPO_ROOT/.ship-hashes.sha256}"

log()  { printf '%s\n' "$*" >&2; }
die()  { printf '%s\n' "$*" >&2; exit 1; }

# The integrity pin (.ship-hashes.sha256) covers the DEFAULT manifest + gate
# scripts. A non-default SHIP_MANIFEST swaps the gate set WITHOUT being covered
# by the pin, so surface it loudly — it must never be an accidental bypass.
if [ "$MANIFEST" != "$DEFAULT_MANIFEST" ]; then
  log "⚠️  Using NON-DEFAULT manifest: $MANIFEST"
  log "    This is NOT covered by the integrity pin — development/testing only."
fi

# Emit clean manifest rows (strip comments/blanks/CR).
manifest_rows() {
  [ -f "$MANIFEST" ] || die "FATAL: manifest not found: $MANIFEST"
  tr -d '\r' < "$MANIFEST" | grep -vE '^[[:space:]]*#' | grep -vE '^[[:space:]]*$'
}

tool_present() { command -v "$1" >/dev/null 2>&1; }

# Test DB reachability for needs-db gates (bash /dev/tcp, no extra deps).
db_up() {
  local host="${DB_HOST:-127.0.0.1}" port="${DB_PORT:-5432}"
  (exec 3<>"/dev/tcp/${host}/${port}") 2>/dev/null || return 1
  return 0
}

# Local version (normalised to the granularity CI pins) for a known tool.
tool_version() {
  case "$1" in
    php)      php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null ;;
    node)     node -e 'process.stdout.write(process.versions.node.split(".")[0])' 2>/dev/null ;;
    pnpm)     pnpm --version 2>/dev/null | cut -d. -f1 ;;
    composer) composer --version 2>/dev/null | sed -n 's/.*version \([0-9][0-9]*\).*/\1/p' | head -1 ;;
    *)        : ;;
  esac
}

json_escape() { printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'; }

# ---------------------------------------------------------------------------
# Supply-chain integrity: verify pinned hashes before executing ANY gate.
# ---------------------------------------------------------------------------
verify_integrity() {
  local strict="$1"
  if [ ! -f "$HASHFILE" ]; then
    [ "$strict" -eq 1 ] && die "SHIP_TAMPERED: $HASHFILE missing; cannot verify gate integrity."
    log "⚠️  $HASHFILE missing — integrity check skipped (run: sh scripts/update-ship-hashes.sh --confirm)"
    return 0
  fi
  if ( cd "$REPO_ROOT" && sha256sum -c "$HASHFILE" >/dev/null 2>&1 ); then
    log "Integrity OK — ship.sh + manifest + ci/gates/* match $HASHFILE."
    return 0
  fi
  ( cd "$REPO_ROOT" && sha256sum -c "$HASHFILE" 2>&1 | grep -viE ': ok$' >&2 ) || true
  die "SHIP_TAMPERED: gate script / manifest hashes do not match $HASHFILE. If the change is intentional, run: sh scripts/update-ship-hashes.sh --confirm"
}

# ---------------------------------------------------------------------------
# Mode A — CI mirror. Runs every locally-runnable gate; records the rest as an
# explicit delta. Returns 0 unless a runnable gate FAILS (or integrity fails).
# ---------------------------------------------------------------------------
run_mirror() {
  local strict="${1:-0}"
  verify_integrity "$strict"
  log "=== Soleil ship — CI mirror (manifest: ci/gates/manifest.tsv) ==="

  local -a PASSED=() FAILED=() DELTAS=()
  local id label script workdir mode tool civ source
  # Parse on US (0x1f), not TAB: tab is IFS-whitespace and `read` would collapse
  # an empty field, silently shifting columns. US is non-whitespace => every
  # field (even empty) is preserved exactly.
  while IFS=$'\037' read -r id label script workdir mode tool civ source; do
    [ -n "${id:-}" ] || continue
    local runnable=0 reason=""
    case "$mode" in
      run)        runnable=1 ;;
      needs-tool) if tool_present "$tool"; then runnable=1; else reason="tool-missing:${tool}"; fi ;;
      needs-db)   if db_up; then runnable=1; else reason="db-down(docker compose up -d db)"; fi ;;
      delta)      reason="ci-only:${source}" ;;
      *)          reason="unknown-mode:${mode}" ;;
    esac

    if [ "$runnable" -eq 1 ]; then
      log ""
      log "--- ${id}: ${label} ---"
      local out rc
      set +e
      out="$(bash "$REPO_ROOT/$script")"
      rc=$?
      set -e
      printf '%s\n' "$out" | grep -E '^GATE_(PASS|FAIL)' >&2 || true
      if [ "$rc" -eq 0 ]; then
        PASSED+=("$id")
      else
        FAILED+=("$id")
      fi
    else
      DELTAS+=("${id}|${reason}|${source}")
      log "--- ${id}: NOT RUN LOCALLY (${reason}) ---"
    fi
  done < <(manifest_rows | tr '\t' '\037')

  local npass=${#PASSED[@]} nfail=${#FAILED[@]} ndelta=${#DELTAS[@]}
  local sha ts
  sha="$(git -C "$REPO_ROOT" rev-parse HEAD 2>/dev/null || echo unknown)"
  ts="$(date -u +%Y%m%dT%H%M%SZ)"

  log ""
  log "Mirror summary: ${npass} passed, ${nfail} failed, ${ndelta} not run locally."

  if [ "$ndelta" -gt 0 ]; then
    log ""
    log "LOCAL-vs-CI DELTA — CI gates NOT executed locally:"
    local d
    for d in "${DELTAS[@]}"; do
      log "  - ${d%%|*}  (${d#*|})"
    done
  fi

  if [ "$nfail" -gt 0 ]; then
    local f joined=""
    for f in "${FAILED[@]}"; do joined="${joined:+$joined,}$f"; done
    log ""
    echo "MIRROR_FAIL commit=${sha} failed=${joined} timestamp=${ts}"
    log "❌ NOT READY — fix the failing gate(s) above."
    return 1
  fi

  if [ "$ndelta" -eq 0 ]; then
    local p joined=""
    for p in "${PASSED[@]}"; do joined="${joined:+$joined,}$p"; done
    echo "MIRROR_PASS commit=${sha} gates=${joined} timestamp=${ts}"
    log "✅ READY TO SHIP — full local mirror of CI gates passed."
    return 0
  fi

  echo "MIRROR_PARTIAL commit=${sha} ran=${npass} not_run=${ndelta} timestamp=${ts}"
  log "⚠️  LOCAL GATES PASSED — but ${ndelta} CI gate(s) were not run locally (see delta above)."
  log "    This is NOT equivalent to CI. To certify a release, commit with"
  log "    [READY TO SHIP]: it re-runs the mirror AND verifies CI is green for this commit."
  return 0
}

# ---------------------------------------------------------------------------
# Mode B — delta report. Runs NO gates. JSON to stdout; human summary to stderr.
# ---------------------------------------------------------------------------
N_BLOCK=0
N_WARN=0
DIFFS_JSON=""
DIFF_SEP=""
DIFFS_HUMAN=""

add_diff() {
  local cat="$1" item="$2" ci="$3" loc="$4" sev="$5"
  DIFFS_JSON="${DIFFS_JSON}${DIFF_SEP}{\"category\": \"$(json_escape "$cat")\", \"item\": \"$(json_escape "$item")\", \"ci\": \"$(json_escape "$ci")\", \"local\": \"$(json_escape "$loc")\", \"severity\": \"$(json_escape "$sev")\"}"
  DIFF_SEP=", "
  DIFFS_HUMAN="${DIFFS_HUMAN}  [${sev}] ${cat}/${item}: ci='${ci}' local='${loc}'\n"
  if [ "$sev" = "blocking" ]; then N_BLOCK=$((N_BLOCK + 1)); else N_WARN=$((N_WARN + 1)); fi
}

run_delta() {
  # Integrity is a blocking difference here (delta never hard-exits early).
  if [ -f "$HASHFILE" ] && ! ( cd "$REPO_ROOT" && sha256sum -c "$HASHFILE" >/dev/null 2>&1 ); then
    add_diff integrity ship-scripts pinned modified blocking
  fi

  # Version-pinned tools (php/node/pnpm) — mismatch or absence is blocking.
  local tool civ local_v
  while IFS=$'\t' read -r tool civ; do
    [ -n "${tool:-}" ] || continue
    if ! tool_present "$tool"; then
      add_diff tool-version "$tool" "$civ" "missing" blocking
      continue
    fi
    local_v="$(tool_version "$tool")"
    if [ "$local_v" != "$civ" ]; then
      add_diff tool-version "$tool" "$civ" "${local_v:-unknown}" blocking
    fi
  done < <(manifest_rows | awk -F'\t' '$7!="-"{print $6"\t"$7}' | sort -u)

  # Presence-only tools used by needs-tool gates — absence is a warning (those
  # CI gates simply become deltas locally).
  local t
  while IFS= read -r t; do
    [ -n "${t:-}" ] || continue
    tool_present "$t" || add_diff tool-presence "$t" "required" "missing" warning
  done < <(manifest_rows | awk -F'\t' '$5=="needs-tool" && $7=="-"{print $6}' | sort -u)

  # Test DB for needs-db gates.
  if manifest_rows | awk -F'\t' '$5=="needs-db"{f=1} END{exit !f}'; then
    db_up || add_diff service test-db "reachable:${DB_HOST:-127.0.0.1}:${DB_PORT:-5432}" "down" warning
  fi

  # Required files CI expects.
  local req
  for req in .env.example backend/composer.lock frontend/pnpm-lock.yaml docker-compose.yml .gitleaks.toml; do
    [ -e "$REPO_ROOT/$req" ] || add_diff file "$req" "present" "missing" blocking
  done

  # Git state (warnings — CI sees the committed tree only).
  if git -C "$REPO_ROOT" rev-parse --git-dir >/dev/null 2>&1; then
    local dirty untracked behind upstream
    dirty="$(git -C "$REPO_ROOT" status --porcelain --untracked-files=no 2>/dev/null | wc -l | tr -d ' ')"
    untracked="$(git -C "$REPO_ROOT" ls-files --others --exclude-standard 2>/dev/null | wc -l | tr -d ' ')"
    [ "${dirty:-0}" -gt 0 ] && add_diff git-state uncommitted-changes "clean-tree" "${dirty}-modified" warning
    [ "${untracked:-0}" -gt 0 ] && add_diff git-state untracked-files "ci-sees-none" "${untracked}-untracked" warning
    if upstream="$(git -C "$REPO_ROOT" rev-parse --abbrev-ref '@{u}' 2>/dev/null)"; then
      behind="$(git -C "$REPO_ROOT" rev-list --count "HEAD..${upstream}" 2>/dev/null || echo 0)"
      [ "${behind:-0}" -gt 0 ] && add_diff git-state behind-remote "up-to-date:${upstream}" "${behind}-behind" warning
    fi
  fi

  local status="compliant" sha
  [ "$((N_BLOCK + N_WARN))" -gt 0 ] && status="non-compliant"
  sha="$(git -C "$REPO_ROOT" rev-parse HEAD 2>/dev/null || echo unknown)"

  printf '{"status": "%s", "commit": "%s", "differences": [%s]}\n' "$status" "$sha" "$DIFFS_JSON"

  if [ "$((N_BLOCK + N_WARN))" -gt 0 ]; then
    log ""
    log "local-vs-CI delta — ${N_BLOCK} blocking, ${N_WARN} warning:"
    printf '%b' "$DIFFS_HUMAN" >&2
  else
    log "Environment is CI-compliant — no differences."
  fi

  [ "$N_BLOCK" -gt 0 ] && return 1
  [ "$N_WARN" -gt 0 ] && return 2
  return 0
}

# ---------------------------------------------------------------------------
# CI success verification (READY TO SHIP only). Fails CLOSED: anything other
# than "all check-runs green for this exact SHA" blocks. 0=green 1=failed
# 2=pending/none 3=unverifiable.
# ---------------------------------------------------------------------------
verify_ci_success() {
  local sha; sha="$(git -C "$REPO_ROOT" rev-parse HEAD)"
  command -v gh >/dev/null 2>&1 || { log "CI check: gh not installed"; return 3; }
  command -v jq >/dev/null 2>&1 || { log "CI check: jq not installed"; return 3; }
  local repo; repo="$(gh repo view --json nameWithOwner -q .nameWithOwner 2>/dev/null || true)"
  [ -n "$repo" ] || { log "CI check: cannot resolve repo (gh not authenticated?)"; return 3; }
  local json; json="$(gh api "repos/${repo}/commits/${sha}/check-runs" 2>/dev/null || true)"
  [ -n "$json" ] || { log "CI check: GitHub API call failed"; return 3; }
  local total pending failed
  total="$(printf '%s' "$json" | jq -r '.total_count // 0')"
  [ "${total:-0}" -gt 0 ] 2>/dev/null || { log "CI check: no check-runs for ${sha}"; return 2; }
  pending="$(printf '%s' "$json" | jq '[.check_runs[]|select(.status!="completed")]|length')"
  failed="$(printf '%s' "$json" | jq '[.check_runs[]|select(.conclusion!=null)|select(["success","neutral","skipped"]|index(.conclusion)|not)]|length')"
  [ "${pending:-0}" -eq 0 ] || { log "CI check: ${pending} run(s) still pending for ${sha}"; return 2; }
  [ "${failed:-0}" -eq 0 ]  || { log "CI check: ${failed} run(s) NOT successful for ${sha}"; return 1; }
  log "CI check: all ${total} check-runs green for ${sha}."
  return 0
}

# ---------------------------------------------------------------------------
# READY TO SHIP certification flow. No flag can skip any sub-check.
# ---------------------------------------------------------------------------
ready_to_ship() {
  log "================================================================"
  log " READY TO SHIP trigger detected — running strict certification."
  log "================================================================"

  # 1. delta precheck — abort on blocking divergence.
  log ""
  log "[1/3] delta precheck"
  set +e
  run_delta >/dev/null
  local drc=$?
  set -e
  if [ "$drc" -eq 1 ]; then
    run_delta >/dev/null 2>&1 || true   # re-emit human summary
    die "SHIP_BLOCKED: local environment diverges from CI. Fix delta first (run: ./scripts/ship.sh --mode=delta)."
  fi
  log "      delta precheck OK (exit ${drc})."

  # 2. full local mirror — every runnable gate must pass.
  log ""
  log "[2/3] CI mirror"
  set +e
  run_mirror 1
  local mrc=$?
  set -e
  [ "$mrc" -eq 0 ] || die "SHIP_BLOCKED: local mirror failed. Fix the failing gate(s) above."

  # 3. CI must be green for this exact commit (covers gates not runnable locally).
  log ""
  log "[3/3] CI success verification (this commit)"
  set +e
  verify_ci_success
  local crc=$?
  set -e
  case "$crc" in
    0) : ;;
    1) die "SHIP_BLOCKED: CI is NOT green for this commit." ;;
    2) die "SHIP_BLOCKED: CI has not finished (or has no runs) for this commit. Push and wait for CI." ;;
    *) die "SHIP_BLOCKED: cannot verify CI status (need authenticated 'gh' + 'jq'). READY TO SHIP requires CI verification — no override." ;;
  esac

  local sha; sha="$(git -C "$REPO_ROOT" rev-parse HEAD)"
  echo "READY_TO_SHIP_VERIFIED commit=${sha}"
  log ""
  log "✅ READY_TO_SHIP_VERIFIED — local mirror + CI both green for ${sha}."
  return 0
}

usage() { sed -n '2,46p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; }

# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
MODE="mirror"
case "${1:-}" in
  --mode=mirror|"") MODE="mirror" ;;
  --mode=delta)     MODE="delta" ;;
  -h|--help)        usage; exit 0 ;;
  *)                die "Unknown argument: ${1}. Run with --help." ;;
esac

# READY TO SHIP trigger overrides the mode and forces certification.
if git -C "$REPO_ROOT" rev-parse --git-dir >/dev/null 2>&1 \
   && git -C "$REPO_ROOT" log -1 --pretty=%B 2>/dev/null | grep -qi "ready to ship"; then
  ready_to_ship
  exit $?
fi

case "$MODE" in
  mirror) run_mirror 0; exit $? ;;
  delta)  run_delta;    exit $? ;;
esac
