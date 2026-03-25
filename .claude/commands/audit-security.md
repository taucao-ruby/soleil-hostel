---
description: "Run a security audit across backend and frontend (OWASP Top 10 + Soleil Hostel business integrity)"
allowed-tools: ["Read", "Grep", "Glob", "Agent"]
disable-model-invocation: true
---

# Security Audit

**Scope confirmation required.** Before executing, state the planned audit scope
(full or focused) and wait for user confirmation.

## Focus Area

$ARGUMENTS

If no focus area specified, run the full audit.

## Setup

Read these for domain context (CLAUDE.md + ARCHITECTURE_FACTS.md are already loaded):
- `skills/laravel/security-secrets-skill.md`
- `skills/react/security-frontend-skill.md`

## Canonical rules

- `.agent/rules/auth-token-safety.md`
- `.agent/rules/security-runtime-hygiene.md`
- `.agent/rules/booking-integrity.md`
- `.agent/rules/backend-preserve-rbac-source-and-request-validation.md`
- `.agent/rules/instruction-surface-and-task-boundaries.md`

## Audit Checklist

### OWASP Top 10
1. **SQL Injection** — Eloquent required; flag any raw SQL without documented justification
2. **XSS** — inspect whether the established sanitization path is still intact
3. **CSRF** — inspect whether the established `sessionStorage` csrf token -> `X-XSRF-TOKEN` header flow is still intact
4. **Broken Auth** — inspect token expiry, revocation, refresh rotation, and dual-mode auth enforcement
5. **Security Misconfiguration** — inspect Sanctum stateful vs stateless domain separation and runtime config discipline
6. **Sensitive Data Exposure** — inspect logs, responses, and fixtures for token/hash/session leakage

### Soleil Hostel Business Integrity
7. **Auth token leaks** — Grep for logging/dumping of token values or session identifiers
8. **Route authorization bypass** — inspect policy and gate coverage against `docs/PERMISSION_MATRIX.md`
9. **Double-booking integrity** — inspect overlap status and soft-delete alignment across app and DB
10. **Date boundary bugs** — inspect half-open `[check_in, check_out)` handling across app and constraint
11. **Soft-delete leakage** — inspect whether soft-deleted bookings still appear in overlap or availability paths
12. **Location_id drift** — inspect whether `bookings.location_id` still follows the trigger-managed contract
13. **Race conditions** — inspect booking create/confirm/cancel lock and transaction coverage
14. **Payment/refund state integrity** — inspect transitions against the documented state machine

## Output

Per finding: `| File:Line | Severity (Critical/High/Medium/Low) | Issue | Suggested Fix |`

## Escalation

If the agent cannot resolve after completing all steps:
1. Stop and preserve all work in progress.
2. Output a structured summary: what was completed, what remains unresolved, and the specific blocker.
3. Surface to the human operator for decision.

## Summary
## Findings
## Residual Risk
