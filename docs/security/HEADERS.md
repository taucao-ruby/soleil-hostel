# 🔒 Security Headers

> A+ grade security headers implementation

## Overview

9 critical security headers implemented via middleware, achieving **A+ security grade**.

---

## Headers Implemented

| Header                       | Value                                          | Protection          |
| ---------------------------- | ---------------------------------------------- | ------------------- |
| Strict-Transport-Security    | `max-age=63072000; includeSubDomains; preload` | HTTPS downgrade     |
| X-Frame-Options              | `DENY`                                         | Clickjacking        |
| X-Content-Type-Options       | `nosniff`                                      | MIME sniffing       |
| Referrer-Policy              | `strict-origin-when-cross-origin`              | Information leakage |
| Permissions-Policy           | `camera=(), microphone=(), geolocation=()`     | Dangerous APIs      |
| Cross-Origin-Opener-Policy   | `same-origin`                                  | Window takeover     |
| Cross-Origin-Embedder-Policy | `require-corp`                                 | Spectre attacks     |
| Cross-Origin-Resource-Policy | `same-origin`                                  | Resource theft      |
| Content-Security-Policy      | Nonce-based, strict-dynamic                    | XSS attacks         |

---

## Implementation

### Middleware

```php
// app/Http/Middleware/SecurityHeaders.php
public function handle(Request $request, Closure $next)
{
    $nonce = Str::random(32);
    $request->attributes->set('csp_nonce', $nonce);

    $response = $next($request);

    $response->headers->set('Strict-Transport-Security',
        'max-age=63072000; includeSubDomains; preload');
    $response->headers->set('X-Frame-Options', 'DENY');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    // ... other headers

    return $response;
}
```

### CSP Strategy

**Development (relaxed):**

```
script-src 'self' 'nonce-xxx' 'unsafe-inline' 'unsafe-eval' localhost:5173
```

**Production (strict):**

```
script-src 'nonce-xxx' 'strict-dynamic'
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
