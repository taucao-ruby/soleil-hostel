---
name: auth
description: "Skill for the Auth area of soleil-hostel. 73 symbols across 22 files."
---

# Auth

73 symbols | 22 files | Cohesion: 82%

## When to Use

- Working with code in `backend/`
- Understanding how setCsrfToken, clearCsrfToken, AuthProvider work
- Modifying auth-related functionality

## Key Files

| File | Symbols |
|------|---------|
| `backend/tests/Feature/Auth/SoleilTokenCookieEncryptionTest.php` | test_cookie_uuid_resolves_user_on_protected_endpoint, test_cookie_fallback_passes_auth_middleware_on_v1_endpoint, test_revoked_token_cookie_returns_401_on_v1_endpoint, test_expired_token_cookie_returns_401_on_v1_endpoint, test_security_headers_present_on_httponly_authenticated_endpoint (+4) |
| `backend/tests/Feature/Auth/EmailVerificationTest.php` | can_check_verification_status_for_verified_user, already_verified_user_gets_success_response, verified_user_cannot_request_resend, verification_notice_returns_verified_status, email_change_clears_verification_status (+3) |
| `backend/tests/Feature/TokenExpirationTest.php` | test_expired_token_returns_401, test_refresh_token_creates_new_and_revokes_old, test_logout_revokes_token, test_cannot_refresh_expired_token, test_logout_all_devices_revokes_all_tokens (+2) |
| `backend/app/Models/PersonalAccessToken.php` | revoke, incrementRefreshCount, revokeOtherDevices, revokeAllUserTokens, isValid (+2) |
| `backend/tests/Feature/Auth/AuthConsolidationTest.php` | test_unified_logout_all_works_with_bearer_token, test_unified_logout_all_works_with_httponly_cookie, test_unified_endpoints_return_401_with_expired_token, test_cookie_mode_takes_precedence, test_unified_endpoints_return_401_with_revoked_token |
| `backend/tests/Unit/Requests/Auth/LoginRequestValidationTest.php` | rules, test_password_requires_minimum_8_characters, test_password_passes_with_8_characters, test_password_is_required, test_email_is_required |
| `backend/tests/Feature/Auth/AuthenticationTest.php` | test_expired_token_returns_401, test_logout_all_devices_revokes_all_tokens, test_login_success_with_valid_credentials, test_refresh_token_creates_new_token |
| `backend/app/Http/Controllers/Auth/UnifiedAuthController.php` | logoutAll, me, logout, detectAuthMode |
| `backend/app/Http/Controllers/Auth/AuthController.php` | refresh, logout, logoutAll, me |
| `backend/app/Http/Controllers/Auth/HttpOnlyTokenController.php` | refresh, logout, me |

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
| `test_expired_token_returns_401` | Method | `backend/tests/Feature/TokenExpirationTest.php` | 102 |
| `test_refresh_token_creates_new_and_revokes_old` | Method | `backend/tests/Feature/TokenExpirationTest.php` | 135 |
| `test_logout_revokes_token` | Method | `backend/tests/Feature/TokenExpirationTest.php` | 193 |
| `test_cannot_refresh_expired_token` | Method | `backend/tests/Feature/TokenExpirationTest.php` | 242 |
| `test_logout_all_devices_revokes_all_tokens` | Method | `backend/tests/Feature/TokenExpirationTest.php` | 274 |
| `test_get_current_user_info_with_token_expiration` | Method | `backend/tests/Feature/TokenExpirationTest.php` | 379 |
| `test_suspicious_activity_revokes_token` | Method | `backend/tests/Feature/TokenExpirationTest.php` | 462 |
| `setUp` | Method | `backend/tests/Feature/CreateBookingConcurrencyTest.php` | 34 |
| `createToken` | Method | `backend/app/Models/User.php` | 154 |
| `test_booking_create_error_returns_localized_message` | Method | `backend/tests/Feature/I18n/LocaleTest.php` | 40 |
| `setUp` | Method | `backend/tests/Feature/Booking/ConcurrentBookingTest.php` | 38 |
| `can_check_verification_status_for_verified_user` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 83 |
| `already_verified_user_gets_success_response` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 152 |
| `verified_user_cannot_request_resend` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 270 |
| `verification_notice_returns_verified_status` | Method | `backend/tests/Feature/Auth/EmailVerificationTest.php` | 393 |

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
| Controllers | 8 calls |
| Models | 7 calls |
| Booking | 3 calls |
| Feature | 2 calls |

## How to Explore

1. `gitnexus_context({name: "setCsrfToken"})` — see callers and callees
2. `gitnexus_query({query: "auth"})` — find related execution flows
3. Read key files listed above for implementation details
