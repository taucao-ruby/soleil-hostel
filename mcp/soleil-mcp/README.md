# Soleil MCP Server

Safe local MCP server for the Soleil Hostel monorepo.

This server is tooling-only. It does not modify application runtime behavior.

## Features

- `repo_overview`: Read-only repository map + verification commands.
- `read_file`: Safe file reads with denylist and size limits.
- `search`: Safe text search with bounded results/snippets.
- `run_verify`: Allowlisted verification commands only.
- `project_invariants`: High-signal domain invariants + source pointers.

## Safety Model

- No arbitrary command execution.
- `run_verify` is restricted to `policy.json` `allowed_commands`.
- `read_file`/`search` deny blocked paths and secret-like files.
- File read size limit enforced (`max_file_size` in policy).
- Output redaction is applied for common secret patterns.
- No outbound network behavior is implemented.

## Prerequisites

- Node.js `>=20` (CI uses Node 20).
- npm (or pnpm/yarn for install, but npm commands below are canonical).
- Repository checkout with `backend/`, `frontend/`, and `docs/` present.

Optional for `run_verify` targets:

- PHP + Composer deps for backend checks.
- Frontend dependencies for typecheck/unit/lint checks.
- Docker for `docker compose config`.

## Install

```bash
cd mcp/soleil-mcp
npm install
```

## Build / Run

```bash
npm run build
npm start
```

Development mode:

```bash
npm run dev
```

Self-test mode (runs one allowlisted verify target without MCP client wiring):

```bash
npm run self-test
# or
npm run dev -- --self-test docker_compose_config
```

## Policy Configuration

`policy.json` is enforced at runtime:

- `allowed_commands`: command allowlist for `run_verify`.
- `blocked_paths`: directory/file prefixes blocked for read/search.
- `blocked_file_patterns`: regex patterns for secret-like filenames.
- `max_file_size`: per-file read/search size cap.
- `timeouts`: default/max timeout bounds.

## Tool Reference

### `repo_overview`

Input:

```json
{}
```

Returns key paths, package/runtime context, CI notes, and verification commands.

### `read_file`

Input:

```json
{ "relative_path": "docs/COMPACT.md" }
```

Constraints:

- relative path only
- blocked path + sensitive filename denylist
- text files only
- max file size from policy

### `search`

Input:

```json
{ "query": "lock_version", "paths": ["backend/app", "docs/backend"] }
```

Returns top matches with `relative_path`, `line`, `snippet`.

### `run_verify`

Input:

```json
{ "verify_target": "docker_compose_config" }
```

Available targets (from `policy.json`):

- `backend_tests`
- `frontend_typecheck`
- `frontend_unit_tests`
- `docker_compose_config`
- `backend_lint`
- `frontend_lint`

Optional controls:

- `timeout_ms`
- `cancel_after_ms`

### `project_invariants`

Input:

```json
{}
```

Returns structured project invariants and source file pointers.

## MCP Client Setup

### Claude Desktop (example)

Use the snippet in `examples/claude_desktop_config.json` and set your absolute path.

Example command target (Windows):

- `C:\Users\Admin\myProject\soleil-hostel\mcp\soleil-mcp\dist\index.js`

Example command target (macOS/Linux/WSL2):

- `/path/to/soleil-hostel/mcp/soleil-mcp/dist/index.js`

### Cursor / Other MCP Clients

Use a stdio MCP server entry that runs:

```bash
node /absolute/path/to/soleil-hostel/mcp/soleil-mcp/dist/index.js
```

If your client supports working directory config, set it to repo root for predictable relative paths.

## Cross-Platform Notes

- Commands are executed with `shell=false` for safer behavior.
- Relative path handling normalizes both `\` and `/` separators.
- Works on Windows, WSL2, Linux, and macOS if required binaries are installed.

## Security Notes

- Do not add credentials to `policy.json` or command args.
- Keep `.env*`, key files, and secret material outside MCP tool access.
- Redaction is best-effort; verify logs before sharing externally.
