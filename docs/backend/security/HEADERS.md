# 🔒 Security Headers

> A+ grade security headers implementation (9/9 critical headers)

## Overview

9 critical security headers implemented via `SecurityHeaders` middleware, achieving **A+ security grade** on SecurityHeaders.com.

**⚠️ KHÔNG CÓ SECURITY HEADERS = MỜI HACKER VÀO NHÀ UỐNG TRÀ**

---

## Headers Implemented

| Header                       | Value (Production)                             | Protection              |
| ---------------------------- | ---------------------------------------------- | ----------------------- |
| Strict-Transport-Security    | `max-age=63072000; includeSubDomains; preload` | HTTPS downgrade attacks |
| X-Frame-Options              | `DENY`                                         | Clickjacking            |
| X-Content-Type-Options       | `nosniff`                                      | MIME sniffing           |
| Referrer-Policy              | `strict-origin-when-cross-origin`              | Information leakage     |
| Permissions-Policy           | `camera=(), microphone=(), geolocation=()`     | Dangerous browser APIs  |
| Cross-Origin-Opener-Policy   | `same-origin`                                  | Window takeover         |
| Cross-Origin-Embedder-Policy | `require-corp`                                 | Spectre exploitation    |
| Cross-Origin-Resource-Policy | `same-origin`                                  | Resource theft          |
| Content-Security-Policy      | Nonce-based + strict-dynamic                   | XSS attacks             |

---

## Implementation

### SecurityHeaders Middleware

```php
// app/Http/Middleware/SecurityHeaders.php

class SecurityHeaders
{
    private string $nonce = '';

    public function handle(Request $request, Closure $next)
    {
        // Generate unique nonce per request
        $this->nonce = Str::random(32);
        $request->attributes->set('csp_nonce', $this->nonce);

        $response = $next($request);

        // Store nonce in response header for frontend
        $response->headers->set('X-CSP-Nonce', $this->nonce);

        return $this->applySecurityHeaders($response);
    }

    private function applySecurityHeaders($response)
    {
        $isProduction = !config('app.debug');

        // 1. HSTS - Force HTTPS
        $response->headers->set('Strict-Transport-Security',
            $isProduction
                ? 'max-age=63072000; includeSubDomains; preload'  // 2 years
                : 'max-age=31536000; includeSubDomains'           // 1 year (dev)
        );

        // 2. Clickjacking protection
        $response->headers->set('X-Frame-Options', 'DENY');

        // 3. MIME sniffing protection
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 4. Referrer control
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 5. Disable dangerous browser APIs
        $response->headers->set('Permissions-Policy', $this->buildPermissionsPolicy());

        // 6-8. Cross-Origin policies
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // 9. CSP (environment-aware)
        $csp = $isProduction
            ? $this->buildCspProduction()
            : $this->buildCspDevelopment();
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
```

### CSP Strategy

**Development (relaxed):**

```
default-src 'self';
script-src 'self' 'nonce-{random}' 'unsafe-inline' 'unsafe-eval' http://localhost:5173;
style-src 'self' 'unsafe-inline';
connect-src 'self' http://localhost:* ws://localhost:*;
```

**Production (strict):**

```
default-src 'self';
script-src 'nonce-{random}' 'strict-dynamic';
style-src 'self' 'nonce-{random}';
object-src 'none';
base-uri 'self';
frame-ancestors 'none';
upgrade-insecure-requests;
```

---

## Usage

### Blade Templates

```blade
<script nonce="@nonce">
  console.log('Protected by CSP');
</script>

<style nonce="@nonce">
  body { background: white; }
</style>
```

### Helper Function

```php
$nonce = csp_nonce();
```

### Vite Integration

The Vite plugin automatically injects nonce into production bundles:

```javascript
// vite.config.ts
plugins: [react(), vitePluginCspNonce()];
```

---

## CSP Violation Reporting

```php
// Endpoint: POST /api/csp-report
// Logs CSP violations for debugging
```

---

## Verification

### Local Check

```bash
# Linux/Mac
bash scripts/check-security-headers.sh http://localhost:8000

# Windows PowerShell
powershell -File scripts/check-security-headers.ps1 -Url "http://localhost:8000"
```

### Online Check

Use [SecurityHeaders.com](https://securityheaders.com) to verify production deployment.

---

## Tests

```bash
php artisan test tests/Feature/Security/SecurityHeadersTest.php
```

| Test                                | Description             |
| ----------------------------------- | ----------------------- |
| `test_hsts_header_present`          | HSTS header exists      |
| `test_x_frame_options_deny`         | Clickjacking protection |
| `test_csp_nonce_generated`          | Nonce generation        |
| `test_all_critical_headers_present` | All 9 headers           |
| ...                                 | 14 total tests          |
