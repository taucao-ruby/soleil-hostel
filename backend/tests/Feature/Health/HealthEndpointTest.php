<?php

namespace Tests\Feature\Health;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): static
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return $this->actingAs($admin, 'sanctum');
    }

    public function test_health_check_requires_admin(): void
    {
        $this->getJson('/api/health')->assertStatus(401);

        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/health')
            ->assertStatus(403);
    }

    public function test_health_check_returns_expected_structure_for_admin(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health');

        $response->assertJsonStructure([
            'status',
            'timestamp',
            'services' => [
                'database' => ['status'],
                'memory' => ['status', 'usage_mb', 'limit_mb'],
            ],
        ])
            ->assertHeader('Cache-Control');
    }

    public function test_health_check_returns_database_up(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health');

        $response->assertJsonPath('services.database.status', 'up');
    }

    public function test_health_check_does_not_leak_exception_messages(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health');

        // Exception messages must NEVER appear; only static error_code is allowed.
        $body = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('Connection refused', $body);
    }

    public function test_liveness_returns_only_status_ok(): void
    {
        $response = $this->getJson('/api/health/live');

        $response->assertStatus(200)
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_liveness_does_not_expose_topology(): void
    {
        $response = $this->getJson('/api/health/live');

        $body = $response->getContent();
        // No service names, drivers, hostnames, timestamps, or memory stats.
        foreach (['database', 'redis', 'cache', 'queue', 'pgsql', 'memory', 'timestamp'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $body,
                "Public liveness endpoint must not expose '{$forbidden}'"
            );
        }
    }

    public function test_readiness_requires_authentication(): void
    {
        $this->getJson('/api/health/ready')->assertStatus(401);
    }

    public function test_readiness_requires_admin_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/health/ready')
            ->assertStatus(403);
    }

    public function test_readiness_returns_expected_structure_for_admin(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health/ready');

        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => ['healthy'],
                'cache' => ['healthy'],
            ],
            'failure_semantics' => ['critical', 'degraded'],
        ]);
    }

    public function test_readiness_database_check_is_healthy_for_admin(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health/ready');

        $response->assertStatus(200)
            ->assertJsonPath('checks.database.healthy', true);
    }

    public function test_readiness_cache_check_is_present_for_admin(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health/ready');

        $response->assertStatus(200)
            ->assertJsonStructure(['checks' => ['cache' => ['healthy']]]);
    }

    public function test_detailed_requires_authentication(): void
    {
        $response = $this->getJson('/api/health/detailed');

        $response->assertStatus(401);
    }

    public function test_detailed_requires_admin_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/health/detailed');

        $response->assertStatus(403);
    }

    public function test_detailed_returns_full_health_for_admin(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health/detailed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'app' => ['name', 'environment', 'debug'],
                'checks' => [
                    'database',
                    'cache',
                    'storage',
                    'queue',
                ],
                'summary' => ['healthy', 'total', 'percentage'],
                'failure_semantics',
                'metrics' => [
                    'memory_usage_mb',
                    'peak_memory_mb',
                    'php_version',
                    'laravel_version',
                ],
            ]);
    }

    public function test_db_endpoint_requires_admin(): void
    {
        $response = $this->getJson('/api/health/db');

        $response->assertStatus(401);
    }

    public function test_db_endpoint_returns_healthy_for_admin(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health/db');

        $response->assertStatus(200)
            ->assertJson([
                'component' => 'database',
                'criticality' => 'critical',
                'healthy' => true,
            ]);
    }

    public function test_cache_endpoint_returns_healthy_for_admin(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health/cache');

        $response->assertStatus(200)
            ->assertJson([
                'component' => 'cache',
                'criticality' => 'degraded',
                'healthy' => true,
            ]);
    }

    public function test_queue_endpoint_returns_healthy_for_admin(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health/queue');

        $response->assertStatus(200)
            ->assertJson([
                'component' => 'queue',
                'criticality' => 'degraded',
                'healthy' => true,
            ]);
    }

    public function test_health_full_alias_works_for_admin(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/health/full');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'checks', 'metrics']);
    }
}
