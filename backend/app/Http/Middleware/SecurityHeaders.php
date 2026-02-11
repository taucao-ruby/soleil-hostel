<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * SecurityHeaders Middleware - Triển khai tất cả security headers 2025
 *
 * ⚠️ KHÔNG CÓ SECURITY HEADERS = MỜI HACKER VÀO NHÀ UỐNG TRÀ
 *
 * Headers được apply:
 * 1. HSTS (Strict-Transport-Security) - Buộc HTTPS, prevent downgrade attacks
 * 2. X-Frame-Options - Prevent clickjacking
 * 3. X-Content-Type-Options - Prevent MIME sniffing
 * 4. Referrer-Policy - Control referrer leakage
 * 5. Permissions-Policy - Disable dangerous APIs (geolocation, camera, etc)
 * 6. Cross-Origin-Opener-Policy - Prevent window takeover
 * 7. Cross-Origin-Embedder-Policy - Prevent Spectre exploitation
 * 8. Cross-Origin-Resource-Policy - Control resource loading
 * 9. Content-Security-Policy - Most critical: prevent XSS, inline script injection
 *
 * CSP Strategy:
 * - Development: Relaxed (allow localhost:5173, unsafe-eval for hot reload)
 * - Production: Strict (nonce-based scripts, hash for inline styles, strict-dynamic)
 */
class SecurityHeaders
{
    private string $nonce = '';

    public function handle(Request $request, Closure $next)
    {
        // Generate nonce for CSP (only once per request)
        $this->nonce = Str::random(32);

        // Store nonce in request for later use
        $request->attributes->set('csp_nonce', $this->nonce);

        // Get response from next middleware
        $response = $next($request);

        // Apply security headers based on environment
        return $this->applySecurityHeaders($response);
    }

    private function applySecurityHeaders($response)
    {
        $isDevelopment = config('app.debug');
        $isProduction = ! $isDevelopment;

        // ========== 1. HSTS (HTTP Strict-Transport-Security) ==========
        // Buộc browser gửi HTTPS, prevent SSL stripping attacks
        // max-age = 63072000 seconds (2 years, standard cho production)
        // includeSubDomains: áp dụng cho tất cả subdomains
        // preload: đưa lên HSTS preload list toàn cầu
        if ($isProduction) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=63072000; includeSubDomains; preload',
                true
            );
        } else {
            // Dev: shorter HSTS để test
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
                true
            );
        }

        // ========== 2. X-Frame-Options ==========
        // Prevent clickjacking attacks (thay đổi nút bấm, hide form fields, etc)
        // DENY: Không cho embed trong bất kỳ frame nào
        $response->headers->set('X-Frame-Options', 'DENY', true);

        // ========== 3. X-Content-Type-Options ==========
        // Prevent MIME sniffing (hacker cố gắng execute image như JS)
        // nosniff: Bắt buộc tuân theo Content-Type, không đoán
        $response->headers->set('X-Content-Type-Options', 'nosniff', true);

        // ========== 4. Referrer-Policy ==========
        // Control mỗi Referrer header leak ra ngoài (privacy + security)
        // strict-origin-when-cross-origin:
        //   - Same-origin: gửi full URL
        //   - Cross-origin: chỉ gửi origin (không path)
        //   - Less secure: không gửi referrer
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin',
            true
        );

        // ========== 5. Permissions-Policy (formerly Feature-Policy) ==========
        // Disable dangerous browser APIs mà hacker có thể abuse
        // () = disabled, *=() = tất cả origin đều bị disable
        $permissionsPolicy = $this->buildPermissionsPolicy();
        $response->headers->set('Permissions-Policy', $permissionsPolicy, true);

        // ========== 6. Cross-Origin-Opener-Policy ==========
        // Prevent một trang web khác open window và control trang ta
        // same-origin: Chỉ allow same-origin openers
        // same-origin-allow-popups: Allow popups nhưng cross-origin opener bị isolate
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin', true);

        // ========== 7. Cross-Origin-Embedder-Policy ==========
        // credentialless: allows cross-origin resources without CORP header, but strips credentials
        // Less restrictive than require-corp while still mitigating Spectre-style attacks
        $response->headers->set('Cross-Origin-Embedder-Policy', 'credentialless', true);

        // ========== 8. Cross-Origin-Resource-Policy ==========
        // Control who can load this resource (prevent clickjacking, Spectre)
        // same-origin: Chỉ same-origin request mới được
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin', true);

        // ========== 9. Content-Security-Policy (CSP) ==========
        // THE MOST CRITICAL HEADER: Prevent XSS, injection, inline code execution
        // Strategy:
        //   - Dev: Relaxed (allow localhost:5173, unsafe-eval, unsafe-inline)
        //   - Prod: Strict (nonce-based, no inline, strict-dynamic)
        $csp = $isDevelopment
            ? $this->buildCspDevelopment()
            : $this->buildCspProduction();

        $response->headers->set('Content-Security-Policy', $csp, true);

        // ========== CSP-Report-Only (Audit Mode) ==========
        // Report CSP violations mà không block (để test)
        // Nếu bật CSP_REPORTING=1 trong .env, dùng report-only thay vì enforce
        if (config('security-headers.csp.reporting_enabled', false) && ! $isDevelopment) {
            $response->headers->set(
                'Content-Security-Policy-Report-Only',
                $csp.'; report-uri /api/csp-violation-report',
                true
            );
        }

        // ========== 10. X-Content-Type-Options (redundant nhưng important) ==========
        // Some old browsers chỉ nhận X-Content-Type-Options
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none', true);

        // Do not leak CSP nonce in response headers.
        $response->headers->remove('X-CSP-Nonce');
        $response->headers->remove('X-Nonce');

        return $response;
    }

    /**
     * Build Permissions-Policy header
     * Cực nghiêm ngặt: disable tất cả dangerous APIs
     */
    private function buildPermissionsPolicy(): string
    {
        return implode(', ', [
            'accelerometer=()',           // Disable accelerometer access
            'ambient-light-sensor=()',    // Disable light sensor
            'camera=()',                  // Disable camera
            'display-capture=()',         // Disable screen capture
            'document-domain=()',         // Disable document.domain modification
            'encrypted-media=()',         // Disable encrypted media
            'fullscreen=()',              // Disable fullscreen API
            'geolocation=()',             // Disable geolocation
            'gyroscope=()',               // Disable gyroscope
            'magnetometer=()',            // Disable magnetometer
            'microphone=()',              // Disable microphone
            'midi=()',                    // Disable MIDI
            'payment=()',                 // Disable Payment Request API
            'picture-in-picture=()',      // Disable PiP
            'publickey-credentials-get=()', // Disable WebAuthn
            'sync-xhr=()',                // Disable synchronous XMLHttpRequest
            'usb=()',                     // Disable USB API
            'xr-spatial-tracking=()',     // Disable WebXR
            'vr=()',                      // Disable VR API
        ]);
    }

    /**
     * CSP cho Development (relaxed)
     * Allow hot reload, unsafe-eval, localhost:5173
     */
    private function buildCspDevelopment(): string
    {
        $viteHost = config('security-headers.vite.dev_host', 'localhost:5173');
        $nonce = $this->nonce;

        return implode('; ', [
            "default-src 'self'",

            // Script: allow nonce, inline dùng trong dev, hot reload, unsafe-eval
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-inline' 'unsafe-eval' {$viteHost} ws://{$viteHost}",

            // Style: allow nonce, inline, Vite assets
            "style-src 'self' 'nonce-{$nonce}' 'unsafe-inline' {$viteHost}",

            // Font: allow same-origin + data URIs (fonts embedded)
            "font-src 'self' data:",

            // Image: allow same-origin + data URIs
            "img-src 'self' data: https:",

            // Connect: allow same-origin + Vite HMR + localhost
            "connect-src 'self' {$viteHost} ws://{$viteHost} wss://{$viteHost} localhost:* ws://localhost:*",

            // Frame: allow self (for embeds)
            "frame-src 'self'",

            // Object: disable (most dangerous)
            "object-src 'none'",

            // Base: restrict to same-origin
            "base-uri 'self'",

            // Form: restrict submission to same-origin
            "form-action 'self'",

            // Frame ancestors: prevent clickjacking
            "frame-ancestors 'none'",

            // Upgrade insecure requests (HTTPS)
            'upgrade-insecure-requests',
        ]);
    }

    /**
     * CSP cho Production (cực nghiêm ngặt)
     * Nonce-based scripts, hash for inline styles, strict-dynamic
     */
    private function buildCspProduction(): string
    {
        $nonce = $this->nonce;
        $domain = config('app.domain', '');

        return implode('; ', [
            "default-src 'none'",

            // Script: ONLY nonce + hashes, strict-dynamic blocks everything else
            // strict-dynamic = hashes/nonces trusted, ignore allow-list
            // This forces inline scripts to use nonce
            "script-src 'nonce-{$nonce}' 'strict-dynamic'",

            // Style: nonce OR hash (inline styles must have nonce)
            "style-src 'nonce-{$nonce}' 'strict-dynamic'",

            // HTML: inline event handlers FORBIDDEN
            "script-src-attr 'none'",

            // Font: same-origin only
            "font-src 'self'",

            // Image: same-origin only
            "img-src 'self'",

            // Connect: API calls to same-origin only
            "connect-src 'self'",

            // Frame: disable (no embedded iframes)
            "frame-src 'none'",

            // Object: disable
            "object-src 'none'",

            // Base URI: prevent href="//" injection
            "base-uri 'self'",

            // Form: same-origin only
            "form-action 'self'",

            // Frame ancestors: prevent clickjacking
            "frame-ancestors 'none'",

            // Upgrade insecure: force HTTPS
            'upgrade-insecure-requests',

            // Sandbox: maximum restrictions
            'sandbox allow-same-origin allow-scripts allow-forms allow-popups',
        ]);
    }
}
