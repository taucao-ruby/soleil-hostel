<?php

namespace Tests\Feature\Health;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/health');

        // May be 'unhealthy' if Redis is unavailable in test env
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
        $response = $this->getJson('/api/health');

        // Status code may be 503 if Redis unavailable in test env
        $response->assertJsonPath('services.database.status', 'up');
    }

    public function test_liveness_returns_ok(): void
    {
        $response = $this->getJson('/api/health/live');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'timestamp'])
            ->assertJson(['status' => 'ok']);
    }

    public function test_readiness_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/health/ready');

        // Status code may be 200 (ok/degraded) depending on Redis availability
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

    public function test_readiness_database_check_is_healthy(): void
    {
        $response = $this->getJson('/api/health/ready');

        $response->assertStatus(200)
            ->assertJsonPath('checks.database.healthy', true);
    }

    public function test_readiness_cache_check_is_present(): void
    {
        $response = $this->getJson('/api/health/ready');

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
        $user = \App\Models\User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/health/detailed');

        $response->assertStatus(403);
    }

    public function test_detailed_returns_full_health_for_admin(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/health/detailed');

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
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/health/db');

        $response->assertStatus(200)
            ->assertJson([
                'component' => 'database',
                'criticality' => 'critical',
                'healthy' => true,
            ]);
    }

    public function test_cache_endpoint_returns_healthy_for_admin(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/health/cache');

        $response->assertStatus(200)
            ->assertJson([
                'component' => 'cache',
                'criticality' => 'degraded',
                'healthy' => true,
            ]);
    }

    public function test_queue_endpoint_returns_healthy_for_admin(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/health/queue');

        $response->assertStatus(200)
            ->assertJson([
                'component' => 'queue',
                'criticality' => 'degraded',
                'healthy' => true,
            ]);
    }

    public function test_health_full_alias_works_for_admin(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/health/full');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'checks', 'metrics']);
    }
}
