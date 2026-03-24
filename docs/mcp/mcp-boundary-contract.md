---
schema_version: 1.0
produced_by_batch: RC1-R2-BND
date: 2026-03-24
server: soleil-mcp
transport: stdio (local only)
authority: mcp/soleil-mcp/readme.md, mcp/soleil-mcp/policy.json
---

# MCP Boundary Contract — soleil-mcp

> Defines operational boundaries, missing template sections, and applicability assessments for the Soleil Hostel MCP server.

## Server Profile

| Property | Value |
|----------|-------|
| Transport | stdio (local only, no network) |
| Tools | 5 read-only + 1 verify (frontend lint) |
| State mutation | None (verify tool runs `npm run lint` which is read-only) |
| Authentication | None (local stdio) |
| External calls | None |

## 1. Idempotency / Retry

**Applicable:** YES (limited)

- All 5 read-only tools (`read_file`, `search`, `list_files`, `get_file_info`, `read_multiple_files`) are **naturally idempotent** — repeated calls with the same arguments produce the same result (assuming no concurrent file system changes).
- The `verify` tool (runs `npm run lint`) is **idempotent** — lint analysis is deterministic for a given file state.
- **Retry policy:** Safe to retry any tool call on timeout or transient failure. No deduplication mechanism needed because no state is mutated.
- **Timeout handling:** Configured per-command in `policy.json` (`timeout_ms` fields). If a tool times out, the caller should retry once before reporting failure.

## 2. Observability

```yaml
section: observability
applicable: false
reason: >
  soleil-mcp is a local stdio server with no network transport, no persistent state,
  and no concurrent users. Observability infrastructure (metrics, tracing, structured
  logging) would add operational overhead disproportionate to the server's scope.
  The server's stdio output is already visible in the calling terminal session.
  If future versions add network transport or multi-user access, this section
  should be re-evaluated.
```

## 3. Fallback / Manual Procedure

**Applicable:** YES

When the MCP server is unavailable or a tool fails:

| Tool | Manual fallback | Command |
|------|----------------|---------|
| `read_file` | Direct file read | `Get-Content <path>` or `cat <path>` |
| `search` | Grep search | `rg "<query>" --glob "<pattern>"` |
| `list_files` | Directory listing | `Get-ChildItem <path> -Recurse` or `fd . <path>` |
| `get_file_info` | File metadata | `Get-Item <path> \| Select-Object *` |
| `read_multiple_files` | Sequential reads | Run `Get-Content` for each file |
| `verify` (frontend lint) | Direct CLI | `cd frontend && npm run lint` |

**Blocked path enforcement:** Manual fallback does NOT enforce the `blocked_paths` and `blocked_file_patterns` from `policy.json`. The operator must manually avoid reading `.env*`, `*.pem`, `*.key`, and paths listed in `policy.json.blocked_paths`.

## 4. Test / Validation Strategy

**Applicable:** YES

### Smoke test (run after any MCP server change)

```bash
# 1. Verify server starts
echo '{"jsonrpc":"2.0","method":"tools/list","id":1}' | node mcp/soleil-mcp/index.js

# 2. Verify read tool works
echo '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"read_file","arguments":{"path":"README.md"}},"id":2}' | node mcp/soleil-mcp/index.js

# 3. Verify blocked path enforcement
echo '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"read_file","arguments":{"path":".env"}},"id":3}' | node mcp/soleil-mcp/index.js
# Expected: error response (blocked by policy)

# 4. Verify verify tool
echo '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"verify","arguments":{"command":"frontend_lint"}},"id":4}' | node mcp/soleil-mcp/index.js
```

### Validation checklist

- [ ] All 6 tools respond without error on valid input
- [ ] Blocked paths return error, not content
- [ ] Blocked file patterns (`.env*`, `*.pem`) return error
- [ ] `max_file_size` (200KB) limit is enforced
- [ ] Verify tool only accepts allowlisted commands from `policy.json`
- [ ] Timeout values in `policy.json` are respected
