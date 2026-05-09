---
description: Review Soleil Hostel changes for security, authorization, secret handling, MCP safety, and booking integrity risk.
disable-model-invocation: true
allowed-tools: Bash Read Grep Glob
---

# Security review

Manually invoked. Read-only. Complements `.claude/agents/security-reviewer.md` (which focuses on application code paths) by also covering the harness surface — MCP, hooks, settings, CI.

## Scope

What this pass MUST cover. Do NOT restate the underlying invariants here — load them from canonical sources before reviewing:

- **RBAC** — Canonical source: `docs/PERMISSION_MATRIX.md`
- **Booking integrity** — Canonical source: `docs/agents/ARCHITECTURE_FACTS.md` + `docs/DB_FACTS.md`
- **Auth tokens** — Canonical source: `docs/agents/ARCHITECTURE_FACTS.md` + `.agent/rules/auth-token-safety.md`
- **Migration constraints** — Canonical source: `docs/DB_FACTS.md` + `.agent/rules/migration-safety.md`

Pass scope:

1. **RBAC drift** — every changed route in `backend/routes/api/v1.php` and `v2.php` must have its middleware compared against `docs/PERMISSION_MATRIX.md`. Flag any new admin/moderator route missing `role:*` middleware or `Gate::authorize`.
2. **Booking integrity** — for any change touching `Booking*`, `*BookingService`, repositories, or migrations: verify the diff still satisfies the invariants in `docs/agents/ARCHITECTURE_FACTS.md` and the PostgreSQL constraints catalogued in `docs/DB_FACTS.md`. Do not paraphrase those invariants in findings; cite the canonical line.
3. **Auth tokens** — verify the diff still satisfies the lifecycle and CSRF contract recorded in `docs/agents/ARCHITECTURE_FACTS.md` and the rule body in `.agent/rules/auth-token-safety.md`.
4. **Secret hygiene** — no `env()` in runtime code (must be `config()`); no committed `.env*`; no secrets in logs, diffs, console, or tests; no plaintext secrets in `docker-compose*` outside `${VAR}` references.
5. **MCP safety** — `mcp/soleil-mcp/policy.json` and any `.mcp.json` (root or plugin) reviewed for:
   - No HTTP transports unless explicitly required
   - `allowed_commands` allowlist, never wildcards
   - `blocked_paths` covers `.env*`, `.git/`, `storage/oauth-*.key`, `node_modules/`, `vendor/`
   - stdio servers do not write to stdout outside the protocol
6. **Hooks safety** — `.claude/hooks/*` and `.claude/plugins/*/hooks/*`:
   - `set -euo pipefail`, all variables quoted
   - No destructive shell (`rm -rf`, `chmod -R`, force pushes, secret echoing)
   - No reads from `.env*`, `.git/`, `storage/oauth-*.key`, `*.pem`, `*.key`
7. **CI exposure** — `.github/workflows/*.yml`:
   - Secrets passed via `secrets.*`, not literal
   - No `pull_request_target` running untrusted PR code with secrets
   - No commands that print env or `secrets`

## How to run

1. `git diff --stat origin/main...HEAD` — list changed files.
2. For each scope above, grep the diff and the affected files.
3. Cross-reference `docs/PERMISSION_MATRIX.md`, `docs/DB_FACTS.md`, `docs/agents/ARCHITECTURE_FACTS.md`.
4. Use the `soleil-review` MCP server (`run_verify`, `repo_overview`) ONLY in read-only mode. Never invoke MCP tools that the policy does not list.

## Output

Use `.claude/output-styles/security-review.md`. For each finding:

- **Severity**: critical / high / medium / low / info
- **Evidence**: file path, line range, exact snippet (no secrets)
- **Why**: which invariant or doc it violates
- **Remediation**: smallest safe change

Tag every claim `[CONFIRMED]`, `[INFERRED]`, `[UNPROVEN]`, or `[ACTION]` per `CLAUDE.md` evidence-separation policy. Untagged claims are a defect.

## Hard rules

- Do not edit files. Do not run mutating commands.
- Do not print secret values, even when redacted — point at the file/line instead.
- Do not invoke `php artisan tinker`, `git push`, `git reset --hard`, or any deny-listed Bash from `.claude/settings.json`.
- Out-of-scope risks → `docs/FINDINGS_BACKLOG.md` (note only; do not append in this pass without confirmation).
