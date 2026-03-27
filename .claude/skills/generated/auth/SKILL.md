---
name: auth
description: "Skill for the Auth area of soleil-hostel. 56 symbols across 19 files."
---

# Auth

56 symbols | 19 files | Cohesion: 86%

## When to Use

- Working with code in `backend/`
- Understanding how setCsrfToken, clearCsrfToken, AuthProvider work
- Modifying auth-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Auth/EmailVerificationTest.php` | unverified_user_cannot_access_verified_routes, can_check_verification_status_for_unverified_user, user_can_verify_email_with_valid_signed_url, expired_verification_link_is_rejected, verification_link_expires_after_configured_time (+8) |
| `backend/tests/Feature/Auth/SoleilTokenCookieEncryptionTest.php` | test_soleil_token_cookie_is_plain_uuid_not_encrypted, test_control_cookie_remains_encrypted, extractCookieValue, test_revoked_token_cookie_returns_401_on_v1_endpoint, test_expired_token_cookie_returns_401_on_v1_endpoint (+1) |
| `backend/tests/Unit/Requests/Auth/LoginRequestValidationTest.php` | rules, test_password_requires_minimum_8_characters, test_password_passes_with_8_characters, test_password_is_required, test_email_is_required |
| `backend/app/Models/PersonalAccessToken.php` | revoke, incrementRefreshCount, revokeOtherDevices, revokeAllUserTokens |
| `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php` | refresh, logout, login, generateDeviceFingerprint |
| `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` | me, logout, logoutAll, detectAuthMode |
| `backend/app/Http/Controllers/Auth/AuthController.php` | refresh, logout, logoutAll |
| `frontend/src/shared/utils/csrf.ts` | setCsrfToken, clearCsrfToken |
| `frontend/src/features/auth/AuthContext.tsx` | AuthProvider, validateToken |
| `backend/tests/Feature/Auth/AuthenticationTest.php` | test_login_success_with_valid_credentials, test_refresh_token_creates_new_token |

## Entry Points

Start here when exploring this area:

- **`setCsrfToken`** (Function) — `frontend/src/shared/utils/csrf.ts:30`
- **`clearCsrfToken`** (Function) — `frontend/src/shared/utils/csrf.ts:40`
- **`AuthProvider`** (Function) — `frontend/src/features/auth/AuthContext.tsx:46`
- **`validateToken`** (Function) — `frontend/src/features/auth/AuthContext.tsx:72`
- **`LoginRequest`** (Class) — `backend/app/Http/Requests/Auth/LoginRequest.php:6`

## Key Symbols

| Symbol | Type | File | Line |
|--------|------|------|------|
| `LoginRequest` | Class | `backend/app/Http/Requests/Auth/LoginRequest.php` | 6 |
| `setCsrfToken` | Function | `frontend/src/shared/utils/csrf.ts` | 30 |
| `clearCsrfToken` | Function | `frontend/src/shared/utils/csrf.ts` | 40 |
| `AuthProvider` | Function | `frontend/src/features/auth/AuthContext.tsx` | 46 |
| `validateToken` | Function | `frontend/src/features/auth/AuthContext.tsx` | 72 |
| `unverified` | Method | `backend/database/factories/UserFactory.php` | 39 |
| `unverified_user_cannot_access_verified_routes` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 29 |
| `can_check_verification_status_for_unverified_user` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 61 |
| `user_can_verify_email_with_valid_signed_url` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 110 |
| `expired_verification_link_is_rejected` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 185 |
| `verification_link_expires_after_configured_time` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 215 |
| `user_can_request_verification_email_resend` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 245 |
| `verification_resend_is_rate_limited` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 345 |
| `verification_notice_returns_unverified_status` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 372 |
| `unverified_user_receives_verification_email_on_login` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 443 |
| `revoke` | Method | `backend/app/Models/PersonalAccessToken.php` | 220 |
| `incrementRefreshCount` | Method | `backend/app/Models/PersonalAccessToken.php` | 274 |
| `revokeOtherDevices` | Method | `backend/app/Models/PersonalAccessToken.php` | 342 |
| `revokeAllUserTokens` | Method | `backend/app/Models/PersonalAccessToken.php` | 366 |
| `test_unified_endpoints_return_401_with_revoked_token` | Method | `backend/tests/Feature/Auth/AuthConsolidationTest.php` | 320 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `LogoutAll → IsExpired` | cross_community | 4 |
| `LogoutAll → IsRevoked` | cross_community | 4 |
| `Me → IsExpired` | cross_community | 4 |
| `Me → IsRevoked` | cross_community | 4 |
| `Logout → IsExpired` | cross_community | 4 |
| `Logout → IsRevoked` | cross_community | 4 |
| `Handle → IsRevoked` | cross_community | 3 |
| `Handle → IsRevoked` | cross_community | 3 |
| `Login → IsRevoked` | cross_community | 3 |
| `Refresh → IsRevoked` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Room | 11 calls |
| Models | 4 calls |

## How to Explore

1. `soleil-ai-review-engine_context({name: "setCsrfToken"})` — see callers and callees
2. `soleil-ai-review-engine_query({query: "auth"})` — find related execution flows
3. Read key files listed above for implementation details
