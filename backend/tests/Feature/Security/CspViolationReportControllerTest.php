<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * CspViolationReportControllerTest
 *
 * Tests the CSP violation reporting endpoint.
 * Browsers send CSP reports when Content Security Policy is violated.
 */
class CspViolationReportControllerTest extends TestCase
{
    /**
     * Test: Valid CSP report returns 204 No Content
     *
     * Browsers send CSP reports with a nested 'csp-report' key.
     */
    public function test_valid_csp_report_returns_204(): void
    {
        $payload = [
            'csp-report' => [
                'document-uri' => 'https://soleilhostel.com/booking',
                'violated-directive' => 'script-src',
                'original-policy' => "script-src 'nonce-abc123'",
                'blocked-uri' => 'https://evil.example.com/malicious.js',
                'source-file' => 'https://soleilhostel.com/app.js',
                'line-number' => 42,
                'column-number' => 1,
                'status-code' => 200,
            ],
        ];

        $response = $this->postJson('/api/csp-violation-report', $payload);

        $response->assertStatus(204);
    }

    /**
     * Test: CSP report with flat keys (non-nested) returns 204
     *
     * Some CSP reporters send flat keys instead of nested csp-report.
     * The controller handles both formats.
     */
    public function test_csp_report_with_flat_keys_returns_204(): void
    {

        $payload = [
            'document-uri' => 'https://soleilhostel.com/',
            'violated-directive' => 'style-src',
            'original-policy' => "style-src 'self'",
            'blocked-uri' => 'https://cdn.example.com/styles.css',
            'status-code' => 200,
        ];

        $response = $this->postJson('/api/csp-violation-report', $payload);

        $response->assertStatus(204);
    }

    /**
     * Test: Empty payload still returns 204 (endpoint is permissive)
     *
     * CSP endpoints should not reject reports — logging is best-effort.
     */
    public function test_empty_payload_returns_204(): void
    {
        $response = $this->postJson('/api/csp-violation-report', []);

        $response->assertStatus(204);
    }

    /**
     * Test: Report with minimal fields returns 204
     */
    public function test_minimal_report_returns_204(): void
    {
        $payload = [
            'csp-report' => [
                'violated-directive' => 'default-src',
            ],
        ];

        $response = $this->postJson('/api/csp-violation-report', $payload);

        $response->assertStatus(204);
    }

    /**
     * Test: Non-POST methods are not allowed
     */
    public function test_get_request_returns_405(): void
    {
        $response = $this->getJson('/api/csp-violation-report');

        $response->assertStatus(405);
    }

    /**
     * Test: Endpoint is accessible without authentication
     *
     * CSP reports are sent by browsers automatically — no auth context.
     */
    public function test_endpoint_accessible_without_auth(): void
    {
        $payload = [
            'document-uri' => 'https://soleilhostel.com/',
            'violated-directive' => 'img-src',
            'blocked-uri' => 'data:',
        ];

        $response = $this->postJson('/api/csp-violation-report', $payload);

        // Should not require authentication
        $response->assertStatus(204);
    }
}
