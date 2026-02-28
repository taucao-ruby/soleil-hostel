<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * SecurityHeaders Middleware - Applies all recommended security response headers (2025 baseline)
 *
 * ⚠️ Missing security headers significantly increases exposure to common web attacks
 *
 * Headers applied:
 * 1. HSTS (Strict-Transport-Security) - Enforce HTTPS, prevent downgrade attacks
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
        // Forces the browser to use HTTPS; prevents SSL-stripping attacks
        // max-age = 63072000 seconds (2 years, standard for production)
        // includeSubDomains: applies the policy to all subdomains
        // preload: registers the domain on the global HSTS preload list
        if ($isProduction) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=63072000; includeSubDomains; preload',
                true
            );
        } else {
            // Dev: shorter HSTS max-age for easier local testing
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
                true
            );
        }

        // ========== 2. X-Frame-Options ==========
        // Prevents clickjacking (button replacement, hidden form field injection, etc.)
        // DENY: Forbids embedding this response in any frame
        $response->headers->set('X-Frame-Options', 'DENY', true);

        // ========== 3. X-Content-Type-Options ==========
        // Prevents MIME-type sniffing (attacker tries to execute an image as JavaScript)
        // nosniff: Requires browsers to honor the declared Content-Type
        $response->headers->set('X-Content-Type-Options', 'nosniff', true);

        // ========== 4. Referrer-Policy ==========
        // Controls how much Referrer information is leaked to external origins
        // strict-origin-when-cross-origin:
        //   - Same-origin: sends full URL
        //   - Cross-origin: sends origin only (no path or query string)
        //   - Insecure: omits the Referrer header
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin',
            true
        );

        // ========== 5. Permissions-Policy (formerly Feature-Policy) ==========
        // Disables browser APIs that could be abused by an attacker
        // () = feature disabled; the list applies to all origins
        $permissionsPolicy = $this->buildPermissionsPolicy();
        $response->headers->set('Permissions-Policy', $permissionsPolicy, true);

        // ========== 6. Cross-Origin-Opener-Policy ==========
        // Prevents other pages from opening and controlling this window
        // same-origin: only same-origin openers are permitted
        // same-origin-allow-popups: allows popups but isolates cross-origin openers
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin', true);

        // ========== 7. Cross-Origin-Embedder-Policy ==========
        // credentialless: allows cross-origin resources without CORP header, but strips credentials
        // Less restrictive than require-corp while still mitigating Spectre-style attacks
        $response->headers->set('Cross-Origin-Embedder-Policy', 'credentialless', true);

        // ========== 8. Cross-Origin-Resource-Policy ==========
        // Control who can load this resource (prevent clickjacking, Spectre)
        // same-origin: only same-origin requests may load this resource
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
        // Reports CSP violations without blocking (for audit/testing)
        // Enable via CSP_REPORTING=1 in .env to use report-only instead of enforce mode
        if (config('security-headers.csp.reporting_enabled', false) && ! $isDevelopment) {
            $response->headers->set(
                'Content-Security-Policy-Report-Only',
                $csp.'; report-uri /api/csp-violation-report',
                true
            );
        }

        // ========== 10. X-Permitted-Cross-Domain-Policies (belt-and-suspenders) ==========
        // Some older browsers recognise only X-Content-Type-Options
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none', true);

        // Do not leak CSP nonce in response headers.
        $response->headers->remove('X-CSP-Nonce');
        $response->headers->remove('X-Nonce');

        return $response;
    }

    /**
     * Build Permissions-Policy header.
     * Extremely strict: disables all potentially dangerous browser APIs.
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
     * CSP for development (relaxed).
     * Allows hot reload, unsafe-eval, and localhost:5173.
     */
    private function buildCspDevelopment(): string
    {
        $viteHost = config('security-headers.vite.dev_host', 'localhost:5173');
        $nonce = $this->nonce;

        return implode('; ', [
            "default-src 'self'",

            // Script: allow nonce, inline scripts for dev tooling, HMR, and unsafe-eval
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-inline' 'unsafe-eval' {$viteHost} ws://{$viteHost}",

            // Style: allow nonce, inline styles for Vite assets
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
     * CSP for production (maximum strictness).
     * Nonce-based scripts, hash for inline styles, strict-dynamic.
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
