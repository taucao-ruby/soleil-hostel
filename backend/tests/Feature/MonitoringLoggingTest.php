<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Tests for Monitoring & Logging Infrastructure.
 */
class MonitoringLoggingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test liveness health check endpoint.
     */
    public function test_liveness_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health/live');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
            ])
            ->assertJson([
                'status' => 'ok',
            ]);
    }

    /**
     * Test readiness health check endpoint structure.
     * Note: Status may be 503 if Redis is not available in test environment.
     */
    public function test_readiness_endpoint_returns_correct_structure(): void
    {
        $response = $this->getJson('/api/health/ready');

        // Accept both 200 (all healthy) and 503 (some unhealthy)
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
        $response = $this->getJson('/api/health/full');

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
     * Test correlation ID is added to request.
     */
    public function test_correlation_id_is_added_to_response(): void
    {
        $response = $this->getJson('/api/health/live');

        $response->assertHeader('X-Correlation-ID');

        $correlationId = $response->headers->get('X-Correlation-ID');
        $this->assertNotNull($correlationId);
        $this->assertStringStartsWith('sol-', $correlationId);
    }

    /**
     * Test correlation ID is propagated from request.
     */
    public function test_correlation_id_is_propagated_from_request(): void
    {
        $customCorrelationId = 'sol-test-1234567890';

        $response = $this->withHeader('X-Correlation-ID', $customCorrelationId)
            ->getJson('/api/health/live');

        $response->assertHeader('X-Correlation-ID', $customCorrelationId);
    }

    /**
     * Test basic health endpoint structure.
     * Note: Status may be 503 if Redis is not available.
     */
    public function test_basic_health_endpoint_returns_correct_structure(): void
    {
        $response = $this->getJson('/api/health');

        // Accept both 200 (all healthy) and 503 (some unhealthy)
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
     * Test database health check reports correctly.
     */
    public function test_database_health_check_is_healthy(): void
    {
        $response = $this->getJson('/api/health/ready');

        $data = $response->json();

        // Database should always be healthy in test environment
        $this->assertTrue($data['checks']['database']['healthy']);
    }

    /**
     * Test correlation ID format is valid.
     */
    public function test_correlation_id_has_correct_format(): void
    {
        $response = $this->getJson('/api/health/live');

        $correlationId = $response->headers->get('X-Correlation-ID');

        // Format: sol-{timestamp}-{random8chars}
        $this->assertMatchesRegularExpression(
            '/^sol-\d+-[a-zA-Z0-9]{8}$/',
            $correlationId
        );
    }
}
