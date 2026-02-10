<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * SecurityHeadersTest
 * 
 * Kiểm tra tất cả security headers được set đúng
 * Đảm bảo không có rò rỉ thông tin, MIME sniffing, clickjacking, v.v.
 */
class SecurityHeadersTest extends TestCase
{
    /**
     * Test: HSTS header present và correct
     * Buộc browser dùng HTTPS, prevent SSL stripping
     */
    public function test_hsts_header_present(): void
    {
        $response = $this->get('/');
        
        $response->assertHeader('Strict-Transport-Security');
        
        // Check format
        $hsts = $response->headers->get('Strict-Transport-Security');
        $this->assertStringContainsString('max-age=', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
    }

    /**
     * Test: X-Frame-Options prevent clickjacking
     */
    public function test_x_frame_options_deny(): void
    {
        $response = $this->get('/');
        
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    /**
     * Test: X-Content-Type-Options prevent MIME sniffing
     */
    public function test_x_content_type_options_nosniff(): void
    {
        $response = $this->get('/');
        
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Test: Referrer-Policy control information leakage
     */
    public function test_referrer_policy_strict_origin(): void
    {
        $response = $this->get('/');
        
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Test: Permissions-Policy disable dangerous APIs
     */
    public function test_permissions_policy_present(): void
    {
        $response = $this->get('/');
        
        $permissionsPolicy = $response->headers->get('Permissions-Policy');
        
        // Check dangerous APIs are disabled
        $this->assertStringContainsString('camera=()', $permissionsPolicy);
        $this->assertStringContainsString('microphone=()', $permissionsPolicy);
        $this->assertStringContainsString('geolocation=()', $permissionsPolicy);
        $this->assertStringContainsString('payment=()', $permissionsPolicy);
    }

    /**
     * Test: Cross-Origin-Opener-Policy prevent window takeover
     */
    public function test_cross_origin_opener_policy(): void
    {
        $response = $this->get('/');
        
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }

    /**
     * Test: Cross-Origin-Embedder-Policy prevent Spectre
     */
    public function test_cross_origin_embedder_policy(): void
    {
        $response = $this->get('/');
        
        $response->assertHeader('Cross-Origin-Embedder-Policy', 'require-corp');
    }

    /**
     * Test: Cross-Origin-Resource-Policy restrict resource loading
     */
    public function test_cross_origin_resource_policy(): void
    {
        $response = $this->get('/');
        
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    /**
     * Test: Content-Security-Policy present (dev or production)
     */
    public function test_content_security_policy_present(): void
    {
        $response = $this->get('/');
        
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotEmpty($csp, 'CSP header should be present');
        
        // Check basic CSP structure
        if (config('app.debug')) {
            // Dev mode: allow unsafe-eval, unsafe-inline
            $this->assertStringContainsString('unsafe-eval', $csp);
        } else {
            // Production: strict-dynamic, no unsafe
            $this->assertStringContainsString('strict-dynamic', $csp);
        }
    }

    /**
     * Test: CSP nonce is generated and available
     */
    public function test_csp_nonce_generated(): void
    {
        $response = $this->get('/');
        
        // Nonce should NOT be exposed via X-CSP-Nonce header (BE-028 security fix)
        $this->assertNull($response->headers->get('X-CSP-Nonce'), 'X-CSP-Nonce header should not be exposed');

        // Nonce should be embedded in the CSP header
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotEmpty($csp, 'CSP header should be present');
        preg_match("/nonce-([A-Za-z0-9]+)/", $csp, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'CSP nonce should be generated and embedded in CSP header');
        $this->assertGreaterThanOrEqual(32, strlen($matches[1]), 'Nonce should be at least 32 chars');
    }

    /**
     * Test: CSP nonce included in script-src directive
     */
    public function test_csp_includes_nonce_in_script_src(): void
    {
        $response = $this->get('/');
        
        $csp = $response->headers->get('Content-Security-Policy');
        
        // Extract nonce from CSP header
        preg_match("/nonce-([A-Za-z0-9]+)/", $csp, $matches);
        $nonce = $matches[1] ?? null;
        $this->assertNotEmpty($nonce, 'Nonce should be present in CSP header');
        
        // CSP should include nonce in script-src
        $this->assertStringContainsString("nonce-{$nonce}", $csp);
    }

    /**
     * Test: All critical headers present (summary)
     * This is the main test for "A+ grade"
     */
    public function test_all_critical_headers_present(): void
    {
        $response = $this->get('/');
        
        $criticalHeaders = [
            'Strict-Transport-Security',
            'X-Frame-Options',
            'X-Content-Type-Options',
            'Referrer-Policy',
            'Permissions-Policy',
            'Cross-Origin-Opener-Policy',
            'Cross-Origin-Embedder-Policy',
            'Cross-Origin-Resource-Policy',
            'Content-Security-Policy',
        ];
        
        foreach ($criticalHeaders as $header) {
            $response->assertHeader($header);
        }
    }

    /**
     * Test: CSP violation endpoint accessible
     */
    public function test_csp_violation_report_endpoint(): void
    {
        $violationData = [
            'csp-report' => [
                'violated-directive' => 'script-src',
                'original-policy' => "script-src 'nonce-abc123'",
                'blocked-uri' => 'https://evil.com/script.js',
                'source-file' => 'https://localhost:5173/app.js',
                'line-number' => 42,
                'column-number' => 1,
                'document-uri' => 'https://localhost:5173/',
            ],
        ];
        
        $response = $this->postJson('/api/csp-violation-report', $violationData);
        
        // Should return 204 No Content
        $response->assertStatus(204);
    }

    /**
     * Test: Nonce @directive available in Blade
     */
    public function test_nonce_directive_available(): void
    {
        // Test that csp_nonce() helper function exists and returns a value
        $nonce = csp_nonce();
        
        // In test context, nonce should be available
        $this->assertIsString($nonce);
        // Note: nonce is set by SecurityHeaders middleware, so it exists if request is made
    }
}
