#!/usr/bin/env bash
# PreToolUse hook: block dangerous bash commands
# Exit 2 = block, Exit 0 = allow

# jq fail-open policy: if jq is missing, emit structured warning and degrade
if ! command -v jq &>/dev/null; then
  echo "SOLEIL-HOOK-DEGRADED: hook=block-dangerous-bash reason=jq-missing timestamp=$(date -u +%Y-%m-%dT%H:%M:%SZ) severity=WARNING" >&2
  # Append to audit log if writable
  audit_log="$(dirname "$0")/../hook-audit.log"
  echo "{\"event\":\"degraded\",\"hook\":\"block-dangerous-bash\",\"reason\":\"jq-missing\",\"timestamp\":\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" >> "$audit_log" 2>/dev/null
  exit 0
fi

cmd=$(cat | jq -r '.tool_input.command // empty')

if [ -z "$cmd" ]; then
  exit 0
fi

# Audit log path
audit_log="$(dirname "$0")/../hook-audit.log"

# Block destructive commands
case "$cmd" in
  *"rm -rf"*|*"git push --force"*|*"git reset --hard"*|*"git checkout -- ."*)
    echo '{"decision":"block","reason":"Destructive command blocked by hook. Use a safer alternative."}' >&2
    echo "{\"event\":\"block\",\"hook\":\"block-dangerous-bash\",\"command\":\"$(echo "$cmd" | head -c 200)\",\"category\":\"destructive\",\"timestamp\":\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" >> "$audit_log" 2>/dev/null
    exit 2 ;;
  *"artisan tinker"*)
    echo '{"decision":"block","reason":"artisan tinker blocked — bypasses service/repository boundaries."}' >&2
    echo "{\"event\":\"block\",\"hook\":\"block-dangerous-bash\",\"command\":\"artisan tinker\",\"category\":\"boundary-bypass\",\"timestamp\":\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" >> "$audit_log" 2>/dev/null
    exit 2 ;;
  *APP_KEY*|*DB_PASSWORD*|*REDIS_PASSWORD*|*SECRET*|*private_key*)
    echo '{"decision":"block","reason":"Command references sensitive credentials — blocked by hook."}' >&2
    echo "{\"event\":\"block\",\"hook\":\"block-dangerous-bash\",\"command\":\"[REDACTED-CREDENTIAL-REF]\",\"category\":\"credential\",\"timestamp\":\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" >> "$audit_log" 2>/dev/null
    exit 2 ;;
esac

exit 0
