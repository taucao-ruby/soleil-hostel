<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests auth, verification, and rate limiting on AI endpoints.
 */
class AiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        config()->set('ai_harness.enabled', true);

        $response = $this->postJson('/api/v1/ai/faq_lookup', ['message' => 'test']);

        $response->assertStatus(401);
    }

    public function test_unverified_user_is_rejected(): void
    {
        config()->set('ai_harness.enabled', true);

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/faq_lookup', ['message' => 'test']);

        // Unverified users get 403 or 409 from the verified middleware
        $this->assertContains($response->status(), [403, 409]);
    }

    public function test_rate_limit_returns_429_after_threshold(): void
    {
        config()->set('ai_harness.enabled', true);

        $user = User::factory()->create();

        // The route has throttle:10,1 — 10 per minute
        for ($i = 0; $i < 11; $i++) {
            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/ai/faq_lookup', ['message' => "test {$i}"]);
        }

        $response->assertStatus(429);
    }
}
