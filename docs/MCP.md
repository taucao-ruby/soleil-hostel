# MCP Server — Soleil Hostel

Documentation for the Soleil MCP (Model Context Protocol) server.

Source: `mcp/soleil-mcp/`

## What MCP Provides

A safe, local MCP server that gives AI coding agents read-only access to the repository plus allowlisted verification commands. It does not modify application runtime behavior.

## Server Location

```
mcp/soleil-mcp/
```

### How to Start

```bash
cd mcp/soleil-mcp
npm install
npm run build
npm start
```

Development mode:

```bash
npm run dev
```

Self-test mode:

```bash
npm run self-test
# or
npm run dev -- --self-test docker_compose_config
```

## Tool List

Source: `mcp/soleil-mcp/src/` and `mcp/soleil-mcp/README.md`

| Tool | Description | Agent-safe | Modifies state |
|------|-------------|------------|----------------|
| `repo_overview` | Read-only repository map + verification commands | Yes | No |
| `read_file` | Safe file read with denylist and size limits | Yes | No |
| `search` | Text search with bounded results/snippets | Yes | No |
| `run_verify` | Execute allowlisted verification commands only | Yes | No (read-only checks) |
| `project_invariants` | Domain invariants + source file pointers | Yes | No |

## Allowlist: Commands Safe for `run_verify`

Source: `mcp/soleil-mcp/policy.json`

| Target | Working Dir | Command | Timeout |
|--------|-------------|---------|---------|
| `backend_tests` | `backend` | `php artisan test` | 15 min |
| `frontend_typecheck` | `frontend` | `npx tsc --noEmit` | 10 min |
| `frontend_unit_tests` | `frontend` | `npx vitest run` | 10 min |
| `docker_compose_config` | `.` | `docker compose config` | 3 min |
| `backend_lint` | `backend` | `php vendor/bin/pint --test` | 5 min |
| `frontend_lint` | `frontend` | `npm run lint` | 5 min |

## Blocked Paths

These paths are denied for `read_file` and `search`:

- `.git`, `node_modules`, `vendor`, `.idea`, `.vscode`
- `backend/storage`, `backend/bootstrap/cache`

## Blocked File Patterns

Regex patterns that block sensitive files:

- `.env*`, `*.pem`, `*.key`, `*.p12`, `*.pfx`, `*.crt`, `*.cer`
- `id_rsa`, `id_ed25519`
- Files matching `credentials` or `secret`

## Safety Constraints

- Max file size per read: 200 KB (`max_file_size: 204800`)
- Max search results: 50
- Snippet chars: 220 per match
- Max files scanned: 5000
- Default timeout: 5 min; max timeout: 20 min
- No arbitrary command execution
- No outbound network behavior
- Output redaction for common secret patterns

## How to Connect an MCP Client

### Claude Desktop

Use the snippet in `mcp/soleil-mcp/examples/claude_desktop_config.json`.

Set absolute path to: `mcp/soleil-mcp/dist/index.js`

Windows example:

```json
{
  "command": "node",
  "args": ["C:\\Users\\Admin\\myProject\\soleil-hostel\\mcp\\soleil-mcp\\dist\\index.js"]
}
```

### Cursor / Other MCP Clients

Use a stdio MCP server entry:

```bash
node /absolute/path/to/soleil-hostel/mcp/soleil-mcp/dist/index.js
```

Set working directory to repo root for predictable relative paths.

## Troubleshooting

| Issue | Solution |
|-------|----------|
| `run_verify` command not found | Ensure dependencies are installed (`composer install`, `npm install`) |
| File read blocked | Check if path matches `blocked_paths` or `blocked_file_patterns` in `policy.json` |
| Timeout on backend tests | Increase `timeout_ms` in `policy.json` or run locally |
| Node not found | Ensure Node >= 20 is on PATH |
| Cross-platform path issues | Server normalizes both `\` and `/` separators |
