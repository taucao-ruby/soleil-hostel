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

## Audit Checklist

### OWASP Top 10
1. **SQL Injection** — Eloquent required; flag any raw SQL without documented justification
2. **XSS** — All user input must pass through `HtmlPurifierService`
3. **CSRF** — Verify `sessionStorage` csrf_token → `X-XSRF-TOKEN` header flow intact
4. **Broken Auth** — Token expiry, revocation, refresh rotation enforcement
5. **Security Misconfiguration** — Sanctum stateful vs stateless domain separation
6. **Sensitive Data Exposure** — Tokens/hashes/session IDs never logged or in API responses

### Soleil Hostel Business Integrity
7. **Auth token leaks** — Grep for logging/dumping of token values or session identifiers
8. **Route authorization bypass** — Policy/Gate::authorize coverage on all protected routes
9. **Double-booking integrity** — Overlap query uses correct statuses + `deleted_at IS NULL`
10. **Date boundary bugs** — Half-open `[check_in, check_out)` consistent across app + constraint
11. **Soft-delete leakage** — Soft-deleted bookings excluded from availability/overlap queries
12. **Location_id drift** — `bookings.location_id` aligned with `rooms.location_id` via trigger
13. **Race conditions** — Booking create/confirm/cancel uses `lockForUpdate()` in transactions
14. **Payment/refund state integrity** — Transitions follow defined state machine

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
