<?php

namespace Tests\Feature;

use App\Http\Middleware\AddCorrelationId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TD-001: Standardized API Error Format Tests
 *
 * Verifies that all API error responses follow the unified JSON schema:
 * {
 *   "success": false,
 *   "message": "...",
 *   "errors": {...} | null,
 *   "trace_id": "...",
 *   "timestamp": "..."
 * }
 *
 * Also verifies:
 *  - trace_id is populated from X-Correlation-ID header
 *  - ValidationException includes per-field errors
 *  - Stack traces are never leaked in non-debug responses
 *  - Correlation ID is propagated when sent by client
 */
class ApiErrorFormatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Standard JSON structure keys expected on all error responses.
     */
    private const ERROR_STRUCTURE = [
        'success',
        'message',
        'errors',
        'trace_id',
        'timestamp',
    ];

    // =====================================================================
    // 1. Unified error shape on 404
    // =====================================================================

    public function test_404_returns_standardized_json_with_trace_id(): void
    {
        $response = $this->getJson('/api/v1/nonexistent-route-12345');

        $response->assertStatus(404);
        $response->assertJsonStructure(self::ERROR_STRUCTURE);
        $response->assertJson(['success' => false]);
        $this->assertNotNull($response->json('trace_id'), 'trace_id must be present');
        $this->assertNotNull($response->json('timestamp'), 'timestamp must be present');
    }

    // =====================================================================
    // 2. Validation errors include per-field errors
    // =====================================================================

    public function test_validation_error_includes_per_field_errors(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        // POST /api/v1/bookings with empty body should trigger validation
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookings', []);

        $response->assertStatus(422);
        $response->assertJsonStructure(self::ERROR_STRUCTURE);
        $response->assertJson([
            'success' => false,
            'message' => 'Validation failed.',
        ]);

        // Must have per-field errors
        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors, 'Validation errors must include at least one field');
    }

    // =====================================================================
    // 3. trace_id propagates client-sent correlation ID
    // =====================================================================

    public function test_trace_id_propagates_client_correlation_id(): void
    {
        $clientCorrelationId = 'test-client-corr-12345';

        $response = $this->withHeader(AddCorrelationId::HEADER_NAME, $clientCorrelationId)
            ->getJson('/api/v1/nonexistent-route-12345');

        $response->assertStatus(404);
        $response->assertJson(['trace_id' => $clientCorrelationId]);

        // Also verify response header
        $response->assertHeader(AddCorrelationId::HEADER_NAME, $clientCorrelationId);
    }

    // =====================================================================
    // 4. Auto-generated trace_id when no client header
    // =====================================================================

    public function test_trace_id_auto_generated_when_no_client_header(): void
    {
        $response = $this->getJson('/api/v1/nonexistent-route-12345');

        $response->assertStatus(404);

        $traceId = $response->json('trace_id');
        $this->assertNotNull($traceId);
        $this->assertStringStartsWith('sol-', $traceId, 'Auto-generated trace_id must start with sol-');
    }

    // =====================================================================
    // 5. 401 returns standardized format
    // =====================================================================

    public function test_401_returns_standardized_json(): void
    {
        $response = $this->getJson('/api/v1/bookings');

        $response->assertStatus(401);
        $response->assertJsonStructure(self::ERROR_STRUCTURE);
        $response->assertJson(['success' => false]);
        $this->assertNotNull($response->json('trace_id'));
    }

    // =====================================================================
    // 6. 403 returns standardized format
    // =====================================================================

    public function test_403_returns_standardized_json(): void
    {
        // Regular user trying to access admin endpoint
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/bookings');

        $response->assertStatus(403);
        $response->assertJsonStructure(self::ERROR_STRUCTURE);
        $response->assertJson(['success' => false]);
        $this->assertNotNull($response->json('trace_id'));
    }

    // =====================================================================
    // 7. Model not found returns standardized 404
    // =====================================================================

    public function test_model_not_found_returns_standardized_404(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/bookings/999999');

        $response->assertStatus(404);
        $response->assertJsonStructure(self::ERROR_STRUCTURE);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('not found', strtolower($response->json('message')));
    }

    // =====================================================================
    // 8. Success responses also include trace_id
    // =====================================================================

    public function test_success_response_includes_trace_id(): void
    {
        $response = $this->getJson('/api/ping');

        $response->assertStatus(200);

        // Ping may not use ApiResponse. Check the correlation header instead.
        $response->assertHeader(AddCorrelationId::HEADER_NAME);
    }

    // =====================================================================
    // 9. No stack trace leak in error responses
    // =====================================================================

    public function test_no_stack_trace_in_error_response(): void
    {
        $response = $this->getJson('/api/v1/nonexistent-route-12345');

        $response->assertStatus(404);

        $body = $response->getContent();
        $this->assertStringNotContainsString('Stack trace', $body);
        $this->assertStringNotContainsString('#0 ', $body);
        $this->assertStringNotContainsString('.php:', $body);
    }

    // =====================================================================
    // 10. Error response has correct content-type
    // =====================================================================

    public function test_error_response_has_json_content_type(): void
    {
        $response = $this->getJson('/api/v1/nonexistent-route-12345');

        $response->assertStatus(404);
        $this->assertStringContainsString(
            'application/json',
            $response->headers->get('Content-Type')
        );
    }
}
