# Workflow: Auth Change

Portable procedure for safely modifying authentication, token handling,
Sanctum middleware, CSRF flow, or the `personal_access_tokens` table.

## STOP Conditions (check before starting)

```
STOP if any of the following are true:
- The change removes the Bearer token auth path
- The change removes the HttpOnly cookie auth path
- The change removes revoked_at or expires_at from token validation
- The change removes withCredentials: true from the shared Axios instance
- The change alters the CSRF flow without reading docs/frontend/SERVICES_LAYER.md
```

## Steps

### 1. LOAD rule

LOAD `.agent/rules/auth-token-safety.md`

Review all invariants and STOP conditions before writing code.

### 2. READ canonical facts

READ `docs/agents/ARCHITECTURE_FACTS.md` § "Authentication" — dual-mode invariants, custom token columns, auth enforcement chain.

Note: Do not reproduce the 9-column token table anywhere — read it from ARCHITECTURE_FACTS.md when needed.

### 3. READ current source files

READ the files you will modify. Minimum:
- Relevant middleware in `backend/app/Http/Middleware/`
- `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php` (if touching cookie auth)
- `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` (if touching unified auth)
- `frontend/src/shared/lib/api.ts` (if touching CSRF or Axios config)

If touching the CSRF interceptor:
READ `docs/frontend/SERVICES_LAYER.md` before modifying `api.ts`.

### 4. IMPLEMENT the change

Requirements:
- Both Bearer and HttpOnly cookie paths remain active after the change.
- Cookie auth chain: `token_identifier` → `token_hash` → token record. Do not query by raw token value.
- Token validity: both `revoked_at IS NULL` AND `expires_at IS NULL OR expires_at > now()` enforced.
- CSRF: `sessionStorage` `csrf_token` → `X-XSRF-TOKEN` header; `withCredentials: true` on Axios.
- No tokens, hashes, or credentials logged or exposed in responses.

### 5. WRITE or UPDATE tests

Required test coverage (per CONTRACT.md DoD: Auth/Token Changes):
- Bearer token path: valid token succeeds, expired fails, revoked fails
- HttpOnly cookie path: valid cookie succeeds, expired fails, revoked fails
- Token expiry enforcement tested
- Token revocation enforcement tested
- Refresh rotation tested (if `refresh_count` / `last_rotated_at` touched)
- Suspicious activity detection verified (if refresh abuse logic touched)

### 6. RUN validation gates

```bash
cd backend && php artisan test tests/Feature/Auth/
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
```

Expected: 0 failures, 0 type errors.

### 7. SECRET AUDIT

Confirm before committing:
- No `APP_KEY`, passwords, token values, or token hashes in any modified file.
- No hardcoded credentials introduced.

## Expected Output

- Both auth paths tested and passing
- Token validity invariants preserved
- CSRF flow intact
- Full test suite passes
- No secrets in committed files
