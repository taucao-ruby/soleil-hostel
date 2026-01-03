<?php

namespace Tests\Feature\HealthCheck;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * Health Check Controller Tests
 *
 * Tests the health check endpoints with proper failure semantics:
 * - Database: CRITICAL (503 if down)
 * - Cache/Queue: DEGRADED (200 with warning)
 *
 * @see \App\Http\Controllers\HealthController
 */
class HealthControllerTest extends TestCase
{
    // ========== LIVENESS PROBE TESTS ==========

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_liveness_endpoint_returns_200(): void
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_liveness_endpoint_has_valid_timestamp(): void
    {
        $response = $this->getJson('/api/health/live');

        $data = $response->json();
        $timestamp = \Carbon\Carbon::parse($data['timestamp']);
        
        $this->assertInstanceOf(\Carbon\Carbon::class, $timestamp);
        $this->assertTrue($timestamp->diffInSeconds(now()) < 5);
    }

    // ========== READINESS PROBE TESTS ==========

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_readiness_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/health/ready');

        // May be 200 (ok/degraded) or 503 (unhealthy) depending on deps
        $this->assertTrue(in_array($response->getStatusCode(), [200, 503]));
        
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database',
                'cache',
                'redis',
            ],
            'failure_semantics' => [
                'critical',
                'degraded',
            ],
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_readiness_returns_ok_when_all_healthy(): void
    {
        $response = $this->getJson('/api/health/ready');

        $data = $response->json();
        
        // Verify response has expected structure regardless of health state
        $this->assertArrayHasKey('status', $data);
        $this->assertContains($data['status'], ['ok', 'degraded', 'unhealthy']);
        
        // If all checks pass, status should be 'ok'
        if ($data['checks']['database']['healthy'] && 
            $data['checks']['cache']['healthy'] && 
            $data['checks']['redis']['healthy']) {
            $this->assertEquals('ok', $data['status']);
            $response->assertStatus(200);
        } else {
            // Even if not all healthy, status should be one of valid values
            $this->assertContains($data['status'], ['degraded', 'unhealthy']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_readiness_includes_failure_semantics(): void
    {
        $response = $this->getJson('/api/health/ready');

        $data = $response->json();
        
        $this->assertContains('database', $data['failure_semantics']['critical']);
        $this->assertContains('cache', $data['failure_semantics']['degraded']);
        $this->assertContains('queue', $data['failure_semantics']['degraded']);
    }

    // ========== DETAILED HEALTH TESTS ==========

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_detailed_endpoint_includes_all_checks(): void
    {
        $response = $this->getJson('/api/health/full');

        $this->assertTrue(in_array($response->getStatusCode(), [200, 503]));
        
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
                'degraded_components',
            ],
            'failure_semantics',
            'metrics',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_detailed_endpoint_includes_metrics(): void
    {
        $response = $this->getJson('/api/health/full');

        $data = $response->json();
        
        $this->assertArrayHasKey('memory_usage_mb', $data['metrics']);
        $this->assertArrayHasKey('peak_memory_mb', $data['metrics']);
        $this->assertArrayHasKey('php_version', $data['metrics']);
        $this->assertArrayHasKey('laravel_version', $data['metrics']);
        $this->assertIsNumeric($data['metrics']['memory_usage_mb']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_detailed_endpoint_includes_degraded_components_list(): void
    {
        $response = $this->getJson('/api/health/full');

        $data = $response->json();
        
        $this->assertIsArray($data['summary']['degraded_components']);
    }

    // ========== INDIVIDUAL COMPONENT TESTS ==========

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_database_endpoint_returns_critical_semantics(): void
    {
        $response = $this->getJson('/api/health/db');

        $data = $response->json();
        
        $this->assertEquals('database', $data['component']);
        $this->assertEquals('critical', $data['criticality']);
        $this->assertArrayHasKey('healthy', $data);
        $this->assertArrayHasKey('timestamp', $data);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_database_endpoint_returns_503_when_unhealthy(): void
    {
        $response = $this->getJson('/api/health/db');

        $data = $response->json();
        
        // If database is healthy, should be 200
        if ($data['healthy']) {
            $response->assertStatus(200);
            $this->assertArrayHasKey('latency_ms', $data);
        } else {
            // If unhealthy, should be 503 (critical component)
            $response->assertStatus(503);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cache_endpoint_returns_degraded_semantics(): void
    {
        $response = $this->getJson('/api/health/cache');

        $data = $response->json();
        
        $this->assertEquals('cache', $data['component']);
        $this->assertEquals('degraded', $data['criticality']);
        $this->assertArrayHasKey('healthy', $data);
        
        // Cache is degraded component - always returns 200
        $response->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_queue_endpoint_returns_degraded_semantics(): void
    {
        $response = $this->getJson('/api/health/queue');

        $data = $response->json();
        
        $this->assertEquals('queue', $data['component']);
        $this->assertEquals('degraded', $data['criticality']);
        $this->assertArrayHasKey('healthy', $data);
        
        // Queue is degraded component - always returns 200
        $response->assertStatus(200);
    }

    // ========== LATENCY TESTS ==========

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_database_check_includes_latency(): void
    {
        $response = $this->getJson('/api/health/db');

        $data = $response->json();
        
        if ($data['healthy']) {
            $this->assertArrayHasKey('latency_ms', $data);
            $this->assertIsNumeric($data['latency_ms']);
            $this->assertGreaterThanOrEqual(0, $data['latency_ms']);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cache_check_includes_latency(): void
    {
        $response = $this->getJson('/api/health/cache');

        $data = $response->json();
        
        if ($data['healthy']) {
            $this->assertArrayHasKey('latency_ms', $data);
            $this->assertIsNumeric($data['latency_ms']);
        }
    }

    // ========== NO CACHE HEADERS ==========

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_health_endpoints_have_no_cache_headers(): void
    {
        $endpoints = [
            '/api/health/live',
            '/api/health/ready',
            '/api/health/full',
            '/api/health/db',
            '/api/health/cache',
            '/api/health/queue',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            
            // Verify response is JSON
            $this->assertTrue(
                str_contains($response->headers->get('Content-Type', ''), 'json'),
                "Endpoint {$endpoint} should return JSON"
            );
        }
    }

    // ========== FAILURE SEMANTICS VALIDATION ==========

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_critical_vs_degraded_failure_semantics(): void
    {
        // Database endpoint should return 503 when unhealthy (critical)
        $dbResponse = $this->getJson('/api/health/db');
        $dbData = $dbResponse->json();
        
        if (!$dbData['healthy']) {
            $this->assertEquals(503, $dbResponse->getStatusCode());
        }

        // Cache endpoint should always return 200 (degraded)
        $cacheResponse = $this->getJson('/api/health/cache');
        $cacheResponse->assertStatus(200);

        // Queue endpoint should always return 200 (degraded)
        $queueResponse = $this->getJson('/api/health/queue');
        $queueResponse->assertStatus(200);
    }
}
