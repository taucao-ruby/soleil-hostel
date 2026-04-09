<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\Models\User;
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
    }

    public function test_ai_endpoint_returns_404_when_flag_is_false(): void
    {
        config()->set('ai_harness.enabled', false);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ai/faq_lookup', ['message' => 'test']);

        $response->assertStatus(404);
    }

    public function test_ai_endpoint_returns_404_for_all_task_types_when_disabled(): void
    {
        config()->set('ai_harness.enabled', false);

        $taskTypes = ['faq_lookup', 'room_discovery', 'booking_status', 'admin_draft'];

        foreach ($taskTypes as $taskType) {
            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson("/api/v1/ai/{$taskType}", ['message' => 'test']);

            $response->assertStatus(404, "Expected 404 for task_type '{$taskType}' when disabled");
        }
    }
}
