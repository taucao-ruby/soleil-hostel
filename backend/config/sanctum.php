<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    // ========== CRITICAL: httpOnly Cookie Authentication ==========
    // Stateful domains cho cookie-based auth (thay vì Bearer token ở Authorization header)
    // Frontend + Backend cùng domain → dùng cookie là chuẩn vàng
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Token Expiration Configuration
    |--------------------------------------------------------------------------
    |
    | CRITICAL: Sanctum default không hỗ trợ token expiration
    | 
    | Giải pháp này implement 2 loại token:
    | 1. short_lived: 1-2 giờ cho web SPA (high security)
    | 2. long_lived: 30-90 ngày cho mobile/remember me (user convenience)
    |
    | Mỗi token PHẢI có expires_at, không có exception
    | null expiration = infinite access = bảo mật thảm họa
    |
    */

    // ========== CRITICAL: Token Expiration ==========
    // Sanctum default không hỗ trợ expiration → implement custom
    // BẮTBUỘC: token phải có expires_at, không có exception
    'expiration' => null, // Custom implementation ở PersonalAccessToken model

    /*
    |--------------------------------------------------------------------------
    | Short-lived Token (Web SPA)
    |--------------------------------------------------------------------------
    |
    | Dùng cho web browser (React/Vue SPA)
    | - Lưu ở sessionStorage (tự xóa khi close browser)
    | - Expire: 1-2 giờ (nhanh hết hạn → lower risk khi leaked)
    | - Nếu hết hạn → refresh endpoint
    |
    | Production best practice:
    | - 1 giờ: aggressive, security-first
    | - 2 giờ: balanced (security + UX)
    |
    | Nếu user inactive > 1h → tự logout
    */
    'short_lived_token_expiration_minutes' => (int) env(
        'SANCTUM_SHORT_LIVED_EXPIRATION_MINUTES',
        60 // 1 giờ (production: 60, development: 1440 = 1 ngày)
    ),

    /*
    |--------------------------------------------------------------------------
    | Long-lived Token (Mobile App + Remember Me)
    |--------------------------------------------------------------------------
    |
    | Dùng cho:
    | 1. Mobile app (store ở secure storage, không logout automatic)
    | 2. "Remember me" feature (30 ngày)
    |
    | Expire: 30-90 ngày tùy theo use case
    | - 30 ngày: standard mobile app
    | - 90 ngày: "Remember me" giống Booking.com
    |
    | Trade-off: longer lifetime = longer risk window khi leaked
    | Mitigated by: refresh token rotation, device tracking, IP checking
    |
    | Nếu long-lived token hết hạn → user phải login lại
    */
    'long_lived_token_expiration_days' => (int) env(
        'SANCTUM_LONG_LIVED_EXPIRATION_DAYS',
        30 // 30 ngày (production: 30, "remember me": 90)
    ),

    /*
    |--------------------------------------------------------------------------
    | Refresh Token Rotation
    |--------------------------------------------------------------------------
    |
    | Khi user gọi /api/auth/refresh:
    | - Cấp token mới (expires_at = now + duration)
    | - Revoke token cũ (revoked_at = now)
    |
    | Nếu refresh_count > threshold → force logout (detect stolen token)
    |
    | Ví dụ: Nếu hacker có token cũ, gọi refresh liên tục
    | → refresh_count tăng nhanh → phát hiện + revoke
    |
    */
    'max_refresh_count_per_hour' => (int) env(
        'SANCTUM_MAX_REFRESH_COUNT_PER_HOUR',
        10 // Max 10 refresh/giờ (suspicious activity > 10)
    ),

    /*
    |--------------------------------------------------------------------------
    | Single Device Login
    |--------------------------------------------------------------------------
    |
    | Khi user login trên device mới:
    | - Option 1: logout device cũ (single device login)
    | - Option 2: keep all devices (multi device login)
    |
    | Enable: true = single device (secure)
    | Disable: false = multi device (user convenience)
    |
    | Most secure: true
    | Production recommendation: true
    |
    */
    'single_device_login' => (bool) env(
        'SANCTUM_SINGLE_DEVICE_LOGIN',
        true // Enable by default (security > convenience)
    ),

    /*
    |--------------------------------------------------------------------------
    | Cleanup Old Tokens
    |--------------------------------------------------------------------------
    |
    | Số ngày trước khi xóa token expired/revoked
    | 
    | Ví dụ: delete_old_tokens_after_days = 7
    * → xóa token hết hạn > 7 ngày
    |
    | Cron job: php artisan schedule:run
    | Frequency: daily (hoặc weekly)
    |
    | IMPORTANT: Không xóa quá nhanh (có thể user submit race condition)
    | BEST: 7-14 ngày trước khi xóa
    |
    */
    'delete_old_tokens_after_days' => (int) env(
        'SANCTUM_DELETE_OLD_TOKENS_AFTER_DAYS',
        7 // Xóa token expired > 7 ngày
    ),

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes (Legacy - Deprecated)
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    | DEPRECATED: Sử dụng short_lived_token_expiration_minutes thay thế
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'soleil_'),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | httpOnly Cookie Configuration
    |--------------------------------------------------------------------------
    |
    | CRITICAL SECURITY: Token trong httpOnly cookie, KHÔNG localStorage
    |
    | httpOnly Cookie Flags:
    | 1. httpOnly=true: JavaScript KHÔNG thể access qua document.cookie
    |    → XSS attack không steal được token
    |
    | 2. Secure=true: HTTPS only (không send qua HTTP)
    |    → Man-in-the-middle không intercept
    |
    | 3. SameSite=Strict: Cookie KHÔNG gửi cross-site
    |    → CSRF attack không được cookie
    |    → XSS worm không thể gửi request sang domain khác
    |
    | 4. Domain=.soleilhostel.local: Share với subdomain
    |    → api.soleilhostel.local + www.soleilhostel.local dùng same cookie
    |
    | 5. Path=/: Accessible từ tất cả routes
    |
    | Token Format:
    | - Stored: UUID (token_identifier) in cookie
    | - Lookup: SHA256(token_identifier) = token_hash in DB
    | - Never: plaintext token in response body
    |
    | Frontend:
    | - Axios: automatically send cookie (credentials: 'include')
    | - CSRF: read from response, send via X-XSRF-TOKEN header
    | - localStorage: COMPLETELY REMOVED (dangerous)
    |
    */
    'cookie_name' => env('SANCTUM_COOKIE_NAME', 'soleil_token'),

    'cookie_secure' => env('APP_ENV') === 'production',

    'cookie_http_only' => true,  // ⚡ CRITICAL: XSS cannot access

    'cookie_same_site' => 'strict',  // ⚡ CRITICAL: CSRF + XSS worm protected

    'cookie_domain' => env('SESSION_DOMAIN', null),  // .soleilhostel.local

    /*
    |--------------------------------------------------------------------------
    | Device Fingerprint Verification
    |--------------------------------------------------------------------------
    |
    | Bind token với device (phòng token theft nếu cookie bị leak)
    |
    | Enable: true = reject token dùng từ device khác
    | Disable: false = accept token từ bất kỳ device (less secure)
    |
    | Trade-off:
    | + More secure (token locked to device)
    | - Less convenient (users changing device → logout)
    |
    | Best: true (security > convenience)
    */
    'verify_device_fingerprint' => (bool) env(
        'SANCTUM_VERIFY_DEVICE_FINGERPRINT',
        true // Enable by default for production security
    ),

];
