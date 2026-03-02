<?php

namespace Tests\Unit\Services;

use App\Services\HealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private HealthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HealthService;
    }

    public function test_basic_check_returns_expected_structure(): void
    {
        $result = $this->service->basicCheck();

        $this->assertContains($result['status'], ['healthy', 'unhealthy']);
        $this->assertContains($result['status_code'], [200, 503]);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('services', $result);
    }

    public function test_basic_check_includes_database_service(): void
    {
        $result = $this->service->basicCheck();

        $this->assertArrayHasKey('database', $result['services']);
        $this->assertEquals('up', $result['services']['database']['status']);
    }

    public function test_basic_check_includes_memory_service(): void
    {
        $result = $this->service->basicCheck();

        $this->assertArrayHasKey('memory', $result['services']);
        $this->assertEquals('ok', $result['services']['memory']['status']);
        $this->assertIsFloat($result['services']['memory']['usage_mb']);
    }

    public function test_readiness_check_returns_expected_structure(): void
    {
        $result = $this->service->readinessCheck();

        // Status depends on Redis availability — may be 'ok' or 'degraded'
        $this->assertContains($result['status'], ['ok', 'degraded', 'unhealthy']);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('database', $result['checks']);
        $this->assertTrue($result['checks']['database']['healthy']);
    }

    public function test_readiness_check_includes_failure_semantics(): void
    {
        $result = $this->service->readinessCheck();

        $this->assertEquals(['database'], $result['failure_semantics']['critical']);
        $this->assertEquals(['cache', 'queue', 'redis'], $result['failure_semantics']['degraded']);
    }

    public function test_detailed_check_includes_all_components(): void
    {
        $result = $this->service->detailedCheck();

        $this->assertArrayHasKey('database', $result['checks']);
        $this->assertArrayHasKey('cache', $result['checks']);
        $this->assertArrayHasKey('storage', $result['checks']);
        $this->assertArrayHasKey('queue', $result['checks']);
    }

    public function test_detailed_check_includes_metrics(): void
    {
        $result = $this->service->detailedCheck();

        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('memory_usage_mb', $result['metrics']);
        $this->assertArrayHasKey('php_version', $result['metrics']);
        $this->assertArrayHasKey('laravel_version', $result['metrics']);
    }

    public function test_detailed_check_includes_summary(): void
    {
        $result = $this->service->detailedCheck();

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('healthy', $result['summary']);
        $this->assertArrayHasKey('total', $result['summary']);
        $this->assertArrayHasKey('percentage', $result['summary']);
    }

    public function test_check_database_returns_healthy(): void
    {
        $result = $this->service->checkDatabase();

        $this->assertTrue($result['healthy']);
        $this->assertArrayHasKey('latency_ms', $result);
        $this->assertArrayHasKey('connection', $result);
    }

    public function test_check_cache_returns_healthy(): void
    {
        $result = $this->service->checkCache();

        $this->assertTrue($result['healthy']);
        $this->assertArrayHasKey('driver', $result);
    }

    public function test_check_storage_returns_healthy(): void
    {
        $result = $this->service->checkStorage();

        $this->assertTrue($result['healthy']);
        $this->assertTrue($result['writable']);
    }

    public function test_check_queue_returns_healthy_for_sync_driver(): void
    {
        // phpunit.xml sets QUEUE_CONNECTION=sync
        $result = $this->service->checkQueue();

        $this->assertTrue($result['healthy']);
        $this->assertEquals('sync', $result['driver']);
    }

    public function test_check_component_delegates_correctly(): void
    {
        $this->assertTrue($this->service->checkComponent('database')['healthy']);
        $this->assertTrue($this->service->checkComponent('cache')['healthy']);
        $this->assertTrue($this->service->checkComponent('storage')['healthy']);
        $this->assertTrue($this->service->checkComponent('queue')['healthy']);
    }

    public function test_check_component_returns_error_for_unknown(): void
    {
        $result = $this->service->checkComponent('unknown');

        $this->assertFalse($result['healthy']);
        $this->assertStringContainsString('Unknown component', $result['error']);
    }

    public function test_critical_components_constant(): void
    {
        $this->assertEquals(['database'], HealthService::CRITICAL_COMPONENTS);
    }

    public function test_degraded_components_constant(): void
    {
        $this->assertEquals(['cache', 'queue', 'redis'], HealthService::DEGRADED_COMPONENTS);
    }
}
