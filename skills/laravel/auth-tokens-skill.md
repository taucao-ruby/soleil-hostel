# Laravel Auth Tokens Skill

Use this skill when changing token issuance, refresh, revocation, cookie auth, or middleware validation in Soleil Hostel.

## When to Use This Skill

- You modify auth controllers under `backend/app/Http/Controllers/Auth/`.
- You touch token middleware (`check_token_valid`, `check_httponly_token`).
- You change `personal_access_tokens` columns, indexes, or token lifecycle.
- You update Bearer mode, HttpOnly-cookie mode, or unified auth detection.

## Non-negotiables

- Always enforce token validity checks:
  - `revoked_at` must be null.
  - `expires_at` must be in the future (or explicitly handled legacy-null case).
- Preserve cookie-token lookup contract:
  - Cookie carries `token_identifier`.
  - Backend hashes identifier and looks up `token_hash`.
- Preserve device binding fields where used:
  - `device_id`
  - `device_fingerprint` (when `sanctum.verify_device_fingerprint` is enabled)
- Preserve rotation and suspicious-activity controls:
  - `refresh_count` thresholds
  - `last_rotated_at` updates on rotation flows
- Never leak tokens or secrets in logs, tests, fixtures, or error messages.
- Keep both auth paths consistent when affected:
  - Bearer token endpoints
  - HttpOnly cookie endpoints

## Implementation Checklist

1. Identify which auth mode is impacted.
   - Bearer, cookie, or both (including unified auth mode detection).
2. Keep middleware checks aligned.
   - `CheckTokenNotRevokedAndNotExpired`
   - `CheckHttpOnlyTokenValid`
3. Preserve token creation and rotation invariants.
   - Set expiry based on token type.
   - Revoke old token during refresh.
   - Carry or increment refresh counters intentionally.
4. Keep cookie security settings intact.
   - `HttpOnly`, `SameSite`, `Secure` by environment, and cookie domain behavior.
5. Update tests for revoke/expiry/refresh/device cases.
   - Include suspicious refresh behavior checks.
6. Review logs and error payloads for sensitive field leakage.

## Verification / DoD

```bash
# Auth-token focused tests
cd backend && php artisan test tests/Feature/TokenExpirationTest.php
cd backend && php artisan test tests/Feature/HttpOnlyCookieAuthenticationTest.php
cd backend && php artisan test tests/Feature/Auth/AuthenticationTest.php
cd backend && php artisan test tests/Feature/Auth/AuthConsolidationTest.php

# Baseline repo gates
cd backend && php artisan test
cd frontend && npx tsc --noEmit
cd frontend && npx vitest run
docker compose config
```

## Common Failure Modes

- Checking only expiry or only revocation, not both.
- Breaking cookie flow by comparing plain identifier against DB value directly.
- Resetting or skipping `refresh_count` unexpectedly during rotation.
- Forgetting device fingerprint validation parity between login and middleware.
- Logging token identifiers, hashed tokens, or auth headers.
- Updating only one auth path and leaving Bearer/Cookie behavior inconsistent.

## References

- `../../AGENTS.md`
- `../../backend/app/Models/PersonalAccessToken.php`
- `../../backend/app/Http/Middleware/CheckTokenNotRevokedAndNotExpired.php`
- `../../backend/app/Http/Middleware/CheckHttpOnlyTokenValid.php`
- `../../backend/app/Http/Controllers/Auth/AuthController.php`
- `../../backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php`
- `../../backend/app/Http/Controllers/Auth/UnifiedAuthController.php`
- `../../backend/config/sanctum.php`
- `../../backend/database/migrations/2025_11_20_000100_add_token_expiration_to_personal_access_tokens.php`
- `../../backend/database/migrations/2025_11_21_150000_add_token_security_columns.php`
- `../../backend/tests/Feature/TokenExpirationTest.php`
- `../../backend/tests/Feature/HttpOnlyCookieAuthenticationTest.php`
