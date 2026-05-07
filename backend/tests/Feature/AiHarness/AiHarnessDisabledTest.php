<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\Models\User;
use App\Services\FeatureFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests that AI endpoints return 404 when AI_HARNESS_ENABLED=false.
 */
class AiHarnessDisabledTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        // Ensure the Redis kill-switch is OFF for these tests.
        // config()->set() is no longer load-bearing — the middleware reads
        // exclusively from Redis via FeatureFlag::killSwitch().
        FeatureFlag::forget('ai_harness.enabled');
    }

    public function test_ai_endpoint_returns_404_when_flag_is_false(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ai/faq_lookup', ['message' => 'test']);

        $response->assertStatus(404);
    }

    public function test_ai_endpoint_returns_404_for_all_task_types_when_disabled(): void
    {
        $taskTypes = ['faq_lookup', 'room_discovery', 'booking_status', 'admin_draft'];

        foreach ($taskTypes as $taskType) {
            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson("/api/v1/ai/{$taskType}", ['message' => 'test']);

            $response->assertStatus(404, "Expected 404 for task_type '{$taskType}' when disabled");
        }
    }
}
