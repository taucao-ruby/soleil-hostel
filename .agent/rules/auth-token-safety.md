---
verified-against: docs/agents/ARCHITECTURE_FACTS.md
section: "Authentication"
last-verified: 2026-03-16
maintained-by: docs-sync
---

# Auth Token Safety — Fast-Load Rule

Load this rule at the start of any task touching authentication, token handling,
cookie auth, CSRF, Sanctum middleware, or the `personal_access_tokens` table.

Full specification: `docs/agents/ARCHITECTURE_FACTS.md` § "Authentication"

## Dual-Mode Invariants

- Bearer token AND HttpOnly cookie are BOTH active simultaneously — neither is optional
- Disabling either mode without explicit product decision is a breaking change
- Both paths must remain tested; do not test only one path

## Cookie Auth Chain

- Cookie auth lookup: `token_identifier` (UUID) → `token_hash` (indexed) → token record
- `token_identifier` is the cookie value; `token_hash` is the indexed DB column for fast lookup
- Do NOT query `personal_access_tokens` by raw token value on the cookie path

## Token Validity (both conditions required, not OR)

- `revoked_at IS NULL` — token must not be revoked
- `expires_at IS NULL OR expires_at > now()` — token must not be expired
- Both conditions enforced by middleware; removing either is a security regression

## CSRF Invariant

- CSRF token stored in `sessionStorage` (key: `csrf_token`)
- Sent as `X-XSRF-TOKEN` request header — not a cookie, not a body parameter
- Axios instance in `frontend/src/shared/lib/api.ts` must keep `withCredentials: true`
- Do not bypass or remove the CSRF interceptor; read `docs/frontend/SERVICES_LAYER.md` first

## STOP Conditions

```
STOP — do not commit if any of these are true:
- Bearer token path removed or disabled
- HttpOnly cookie path removed or disabled
- Cookie auth queries by raw token value instead of token_identifier → token_hash chain
- revoked_at check removed from token validation middleware
- expires_at check removed from token validation middleware
- withCredentials: true removed from the shared Axios instance
- X-XSRF-TOKEN header bypassed or replaced with cookie-based CSRF
```
