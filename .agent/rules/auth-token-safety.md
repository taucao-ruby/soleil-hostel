---
verified-against: docs/agents/ARCHITECTURE_FACTS.md
section: "Authentication"
last-verified: 2026-03-16
maintained-by: docs-sync
---

# Auth Token Safety

## Purpose
Preserve the repository's dual-mode auth contract, token validity checks, and CSRF flow.

## Rule
- Bearer-token auth and HttpOnly-cookie auth both remain active unless a higher-authority product decision changes the contract.
- Cookie auth lookup remains `token_identifier` -> `token_hash`; do not query the cookie path by raw token value.
- Token validity enforcement keeps both revocation and expiry checks in middleware.
- Frontend cookie auth keeps `sessionStorage` `csrf_token` -> `X-XSRF-TOKEN` header injection and `withCredentials: true` on the shared API client.
- Token, session, and auth artifacts must not be exposed in logs, fixtures, or user-visible error payloads.

## Why it exists
These constraints prevent auth regressions, token replay gaps, broken cookie sessions, and CSRF bypasses.

## Applies to
Agents, humans, skills, commands, reviews, and tests touching authentication, Sanctum middleware, token storage, cookie auth, or frontend auth transport.

## Violations
- Removing either Bearer or HttpOnly-cookie auth without an approved higher-layer change.
- Querying `personal_access_tokens` by raw cookie value instead of `token_identifier` -> `token_hash`.
- Dropping `revoked_at`, `expires_at`, CSRF header injection, or `withCredentials: true`.
- Logging token identifiers, hashed tokens, auth headers, or session secrets.

## Enforcement
- Canonical source: `docs/agents/ARCHITECTURE_FACTS.md` § "Authentication".
- Runtime enforcement: auth middleware and the shared frontend API client.
- Review and validation: `tests/Feature/TokenExpirationTest.php`, `tests/Feature/HttpOnlyCookieAuthenticationTest.php`, `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Auth/AuthConsolidationTest.php`, `.claude/commands/audit-security.md`, `.claude/commands/review-pr.md`.

## Linked skills / hooks
- `skills/laravel/auth-tokens-skill.md`
- `skills/react/api-client-skill.md`
- `skills/react/security-frontend-skill.md`
