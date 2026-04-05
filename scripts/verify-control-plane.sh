#!/usr/bin/env bash
# verify-control-plane.sh — Replayable control-plane health check
# Run from repo root: bash scripts/verify-control-plane.sh
# Exit code: 0 = all pass, 1 = at least one FAIL
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PASS=0
FAIL=0
WARN=0

check() {
  local name="$1"
  local status="$2"
  local detail="${3:-}"
  if [ "$status" = "PASS" ]; then
    PASS=$((PASS + 1))
    printf "  [PASS] %s\n" "$name"
  elif [ "$status" = "WARN" ]; then
    WARN=$((WARN + 1))
    printf "  [WARN] %s — %s\n" "$name" "$detail"
  else
    FAIL=$((FAIL + 1))
    printf "  [FAIL] %s — %s\n" "$name" "$detail"
  fi
}

echo "=== Soleil Hostel — Control Plane Verification ==="
echo "Date: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "Root: $REPO_ROOT"
echo ""

# --- 1. Prerequisites ---
echo "[1/7] Prerequisites"

if command -v jq &>/dev/null; then
  check "jq installed" "PASS"
else
  check "jq installed" "FAIL" "jq not found — Claude Code hooks are in fail-open mode. Install: https://jqlang.github.io/jq/download/"
fi

if command -v node &>/dev/null; then
  check "node installed" "PASS"
else
  check "node installed" "FAIL" "node not found"
fi

if command -v php &>/dev/null; then
  check "php installed" "PASS"
else
  check "php installed" "WARN" "php not found — backend gates will not run"
fi

echo ""

# --- 2. Hook scripts exist and are readable ---
echo "[2/7] Hook scripts"

for hook in block-dangerous-bash.sh guard-sensitive-files.sh remind-frontend-validation.sh; do
  hook_path="$REPO_ROOT/.claude/hooks/$hook"
  if [ -f "$hook_path" ]; then
    check "hook: $hook exists" "PASS"
  else
    check "hook: $hook exists" "FAIL" "file not found: $hook_path"
  fi
done

echo ""

# --- 3. settings.json valid ---
echo "[3/7] Settings validation"

settings_path="$REPO_ROOT/.claude/settings.json"
if [ -f "$settings_path" ]; then
  if command -v jq &>/dev/null; then
    if jq empty "$settings_path" 2>/dev/null; then
      check "settings.json valid JSON" "PASS"
    else
      check "settings.json valid JSON" "FAIL" "JSON parse error"
    fi
  elif command -v node &>/dev/null; then
    if node -e "JSON.parse(require('fs').readFileSync(process.argv[1],'utf8'))" -- "$settings_path" 2>/dev/null; then
      check "settings.json valid JSON" "PASS"
    else
      check "settings.json valid JSON" "FAIL" "JSON parse error"
    fi
  else
    check "settings.json valid JSON" "WARN" "no jq or node to validate"
  fi
else
  check "settings.json exists" "FAIL" "file not found"
fi

echo ""

# --- 4. MCP policy parseable ---
echo "[4/7] MCP policy"

policy_path="$REPO_ROOT/mcp/soleil-mcp/policy.json"
if [ -f "$policy_path" ]; then
  if command -v jq &>/dev/null; then
    if jq empty "$policy_path" 2>/dev/null; then
      check "policy.json valid JSON" "PASS"
    else
      check "policy.json valid JSON" "FAIL" "JSON parse error"
    fi
  elif command -v node &>/dev/null; then
    if node -e "JSON.parse(require('fs').readFileSync(process.argv[1],'utf8'))" -- "$policy_path" 2>/dev/null; then
      check "policy.json valid JSON" "PASS"
    else
      check "policy.json valid JSON" "FAIL" "JSON parse error"
    fi
  else
    check "policy.json valid JSON" "WARN" "no jq or node to validate"
  fi
else
  check "policy.json exists" "FAIL" "file not found"
fi

echo ""

# --- 5. Rules have verified-against frontmatter ---
echo "[5/7] Rule file freshness"

rules_dir="$REPO_ROOT/.agent/rules"
if [ -d "$rules_dir" ]; then
  for rule_file in "$rules_dir"/*.md; do
    rule_name="$(basename "$rule_file")"
    if grep -q "^verified-against:" "$rule_file" 2>/dev/null; then
      # Check last-verified date
      last_verified=$(grep "^last-verified:" "$rule_file" 2>/dev/null | head -1 | sed 's/last-verified: *//')
      if [ -n "$last_verified" ]; then
        # Check if older than 90 days (approximate: compare YYYY-MM-DD strings)
        today=$(date -u +%Y-%m-%d)
        cutoff=$(date -u -d "90 days ago" +%Y-%m-%d 2>/dev/null || date -u -v-90d +%Y-%m-%d 2>/dev/null || echo "")
        if [ -n "$cutoff" ] && [[ "$last_verified" < "$cutoff" ]]; then
          check "rule: $rule_name" "WARN" "last-verified $last_verified is older than 90 days"
        else
          check "rule: $rule_name" "PASS"
        fi
      else
        check "rule: $rule_name" "WARN" "has verified-against but missing last-verified date"
      fi
    else
      check "rule: $rule_name" "FAIL" "missing verified-against frontmatter"
    fi
  done
else
  check "rules directory" "FAIL" ".agent/rules/ not found"
fi

echo ""

# --- 6. rooms.status deprecation check ---
echo "[6/7] Deprecation guards"

new_status_refs=$(grep -rl "rooms\.status\|->status\b" "$REPO_ROOT/backend/app" --include="*.php" 2>/dev/null | grep -v "readiness_status" | head -20 || true)
if [ -n "$new_status_refs" ]; then
  count=$(echo "$new_status_refs" | wc -l | tr -d ' ')
  check "rooms.status references" "WARN" "$count files still reference rooms.status (legacy field — see DB_FACTS.md deprecation plan)"
else
  check "rooms.status references" "PASS"
fi

echo ""

# --- 7. Key governance files exist ---
echo "[7/7] Governance file existence"

for doc in \
  "CLAUDE.md" \
  "docs/agents/ARCHITECTURE_FACTS.md" \
  "docs/agents/CONTRACT.md" \
  "docs/agents/COMMANDS.md" \
  "docs/PERMISSION_MATRIX.md" \
  "docs/DB_FACTS.md" \
  "docs/agents/CONTROL_PLANE_OWNERSHIP.md" \
  "docs/agents/TASK_BUNDLES.md"; do
  if [ -f "$REPO_ROOT/$doc" ]; then
    check "$doc" "PASS"
  else
    check "$doc" "FAIL" "file not found"
  fi
done

echo ""

# --- Summary ---
echo "=== Summary ==="
total=$((PASS + FAIL + WARN))
printf "  PASS: %d / %d\n" "$PASS" "$total"
printf "  WARN: %d / %d\n" "$WARN" "$total"
printf "  FAIL: %d / %d\n" "$FAIL" "$total"
echo ""

if [ "$FAIL" -gt 0 ]; then
  echo "VERDICT: FAIL — $FAIL check(s) failed. Fix before proceeding."
  exit 1
else
  if [ "$WARN" -gt 0 ]; then
    echo "VERDICT: PASS WITH WARNINGS — $WARN warning(s). Review before release."
  else
    echo "VERDICT: PASS — all checks passed."
  fi
  exit 0
fi
