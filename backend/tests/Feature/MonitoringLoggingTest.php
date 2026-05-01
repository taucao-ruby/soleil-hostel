<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Monitoring & Logging Infrastructure.
 */
class MonitoringLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): static
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return $this->actingAs($admin, 'sanctum');
    }

    /**
     * Test liveness health check endpoint.
     *
     * OBS-002: Public liveness probe MUST return only {"status":"ok"} with
     * no other keys, regardless of underlying service state.
     */
    public function test_liveness_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health/live');

        $response->assertStatus(200)
            ->assertExactJson(['status' => 'ok']);
    }

    /**
     * OBS-002: readiness exposes topology — must require admin auth.
     */
    public function test_readiness_endpoint_returns_correct_structure(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health/ready');

        $this->assertContains($response->status(), [200, 503]);

        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => ['healthy'],
                'cache' => ['healthy'],
                'redis' => ['healthy'],
            ],
        ]);
    }

    /**
     * Test detailed health check endpoint structure.
     * Note: Status may be 503 if external services are not available.
     */
    public function test_detailed_health_endpoint_returns_correct_structure(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health/full');

        // Accept both 200 (all healthy) and 503 (some unhealthy)
        $this->assertContains($response->status(), [200, 503]);

        $response->assertJsonStructure([
            'status',
            'timestamp',
            'app' => [
                'name',
                'environment',
                'debug',
            ],
            'checks' => [
                'database',
                'cache',
                'redis',
                'storage',
                'queue',
            ],
            'summary' => [
                'healthy',
                'total',
                'percentage',
            ],
            'metrics' => [
                'memory_usage_mb',
                'peak_memory_mb',
                'php_version',
                'laravel_version',
            ],
        ]);
    }

    /**
     * OBS-001: Server-generated correlation ID is present and is a UUID.
     */
    public function test_correlation_id_is_added_to_response(): void
    {
        $response = $this->getJson('/api/health/live');

        $response->assertHeader('X-Correlation-ID');

        $correlationId = $response->headers->get('X-Correlation-ID');
        $this->assertNotNull($correlationId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $correlationId
        );
    }

    /**
     * OBS-001: A client-supplied correlation ID is NEVER trusted as the
     * server identifier. The server still generates its own UUID.
     */
    public function test_correlation_id_is_never_propagated_from_request(): void
    {
        $clientSupplied = 'client-supplied-12345';

        $response = $this->withHeader('X-Correlation-ID', $clientSupplied)
            ->getJson('/api/health/live');

        $serverId = $response->headers->get('X-Correlation-ID');
        $this->assertNotSame($clientSupplied, $serverId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $serverId
        );

        // Validated client value is echoed in a distinct header
        $response->assertHeader('X-Client-Correlation-ID', $clientSupplied);
    }

    /**
     * OBS-002: /api/health is a detailed view; admin-only.
     */
    public function test_basic_health_endpoint_returns_correct_structure(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health');

        $this->assertContains($response->status(), [200, 503]);

        $response->assertJsonStructure([
            'status',
            'timestamp',
            'services',
        ]);
    }

    /**
     * Test ping endpoint returns ok.
     */
    public function test_ping_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/ping');

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'API is working!',
            ]);
    }

    /**
     * Test that response contains expected performance-related headers.
     */
    public function test_response_has_standard_headers(): void
    {
        $response = $this->getJson('/api/health/live');

        $response->assertStatus(200);

        // Verify correlation ID header exists
        $this->assertTrue($response->headers->has('X-Correlation-ID'));
    }

    /**
     * Test database health check reports correctly via admin readiness path.
     */
    public function test_database_health_check_is_healthy(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health/ready');

        $data = $response->json();

        $this->assertTrue($data['checks']['database']['healthy']);
    }

    /**
     * OBS-001: server correlation ID format is UUID v4.
     */
    public function test_correlation_id_has_correct_format(): void
    {
        $response = $this->getJson('/api/health/live');

        $correlationId = $response->headers->get('X-Correlation-ID');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $correlationId
        );
    }
}
