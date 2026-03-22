---
name: auth
description: "Skill for the Auth area of soleil-hostel. 50 symbols across 16 files."
---

# Auth

50 symbols | 16 files | Cohesion: 86%

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
| `backend/app/Http/Controllers/Auth/AuthController.php` | refresh, logout, logoutAll |
| `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` | logout, logoutAll |
| `frontend/src/shared/utils/csrf.ts` | setCsrfToken, clearCsrfToken |
| `frontend/src/features/auth/AuthContext.tsx` | AuthProvider, validateToken |
| `frontend/src/features/auth/RegisterPage.tsx` | validate, handleSubmit |

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
| `revoke` | Method | `backend/app/Models/PersonalAccessToken.php` | 220 |
| `incrementRefreshCount` | Method | `backend/app/Models/PersonalAccessToken.php` | 274 |
| `revokeOtherDevices` | Method | `backend/app/Models/PersonalAccessToken.php` | 342 |
| `revokeAllUserTokens` | Method | `backend/app/Models/PersonalAccessToken.php` | 366 |
| `test_unified_endpoints_return_401_with_revoked_token` | Method | `backend/tests/Feature/Auth/AuthConsolidationTest.php` | 320 |
| `logout` | Method | `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` | 68 |
| `logoutAll` | Method | `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` | 89 |
| `refresh` | Method | `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php` | 156 |
| `logout` | Method | `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php` | 247 |
| `refresh` | Method | `backend/app/Http/Controllers/Auth/AuthController.php` | 166 |
| `logout` | Method | `backend/app/Http/Controllers/Auth/AuthController.php` | 277 |
| `logoutAll` | Method | `backend/app/Http/Controllers/Auth/AuthController.php` | 318 |
| `unverified` | Method | `backend/database/factories/UserFactory.php` | 39 |
| `unverified_user_cannot_access_verified_routes` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 29 |
| `can_check_verification_status_for_unverified_user` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 61 |

## Execution Flows

| Flow | Type | Steps |
|------|------|-------|
| `LogoutAll → IsExpired` | cross_community | 4 |
| `LogoutAll → IsRevoked` | cross_community | 4 |
| `Logout → IsExpired` | cross_community | 4 |
| `Logout → IsRevoked` | cross_community | 4 |
| `Handle → IsRevoked` | cross_community | 3 |
| `Handle → IsRevoked` | cross_community | 3 |
| `Login → IsRevoked` | cross_community | 3 |
| `Refresh → IsRevoked` | cross_community | 3 |
| `Refresh → IsRevoked` | cross_community | 3 |
| `RevokeOtherDevices → IsRevoked` | cross_community | 3 |

## Connected Areas

| Area | Connections |
|------|-------------|
| Feature | 8 calls |
| Models | 5 calls |
| Room | 3 calls |

## How to Explore

1. `gitnexus_context({name: "setCsrfToken"})` — see callers and callees
2. `gitnexus_query({query: "auth"})` — find related execution flows
3. Read key files listed above for implementation details
