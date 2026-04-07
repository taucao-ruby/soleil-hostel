---
name: Security Review
description: Business-logic and OWASP security review for auth, booking, payment, and RBAC surfaces
keep-coding-instructions: true
---

Trigger: auth / booking / payment / RBAC / business-logic security review.

Every finding must be tagged with exactly one confidence level:
- `[CONFIRMED]` — exploit path verified in inspected source code
- `[INFERRED]` — likely vulnerability based on pattern analysis, not directly proven
- `[UNPROVEN]` — requires runtime testing or environment-specific validation
- `[ACTION]` — recommended remediation with owner and priority

Repo evidence ≠ runtime proof. Any claim about runtime behavior that was not tested must be explicitly tagged `[UNPROVEN]`.

## Structure

### Surface Reviewed
List files and directories actually inspected. Do not claim review of files not read.

### Assets at Risk
What is at stake: user data, booking integrity, payment state, session tokens, etc.

### Confirmed Vulnerabilities
Table: `| # | File:Line | Severity | Exploit Path | Preconditions | Evidence | Recommended Fix |`
Only `[CONFIRMED]` items. Severity: Critical / High / Medium / Low.

### Business Logic Risks
Table: `| # | Risk | Domain | Evidence | Confidence |`
Focus areas:
- Double-booking / overlap bypass
- TOCTOU in booking write paths
- Restore-overlap (soft-delete restore reintroducing conflicts)
- Privilege escalation (moderator → admin, user → moderator)
- Payment state machine violations
- Cancellation/refund race conditions

### Hardening Recommendations
**Immediate** — fixes for confirmed vulnerabilities.
**Near-term** — mitigations for `[INFERRED]` risks.
**Optional** — defense-in-depth improvements.

### Unproven Runtime Claims
List every statement about runtime behavior that was not directly tested.
Format: `| Claim | Why Unproven | What Would Confirm It |`
Do not omit this section. If empty, state "All claims confirmed in source."
