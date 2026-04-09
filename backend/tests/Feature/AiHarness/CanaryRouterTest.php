<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\AiHarness\Providers\ModelProviderInterface;
use App\AiHarness\Providers\RawModelResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanaryRouterTest extends TestCase
{
    use RefreshDatabase;

    public function test_canary_bypass_returns_support_contact(): void
    {
        config()->set('ai_harness.enabled', true);
        config()->set('ai_harness.canary.faq_lookup_percentage', 0); // 0% = always bypass

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/faq_lookup', ['message' => 'Test canary bypass']);

        $response->assertOk();

        $data = $response->json('data');
        $this->assertFalse($data['canary']);
        $this->assertArrayHasKey('support_contact', $data);
    }

    public function test_canary_active_routes_to_pipeline(): void
    {
        config()->set('ai_harness.enabled', true);
        config()->set('ai_harness.canary.faq_lookup_percentage', 100); // 100% = always canary

        $this->seed(\Database\Seeders\PolicyDocumentSeeder::class);

        $mock = $this->createMock(ModelProviderInterface::class);
        $mock->method('complete')->willReturn(new RawModelResponse(
            providerName: 'anthropic',
            rawContent: 'Giờ nhận phòng là 14:00. [source: checkin-checkout-policy, verified: 2026-04-09]',
            promptTokens: 100,
            completionTokens: 50,
            latencyMs: 200,
        ));
        $mock->method('isAvailable')->willReturn(true);
        $mock->method('getProviderName')->willReturn('anthropic');
        $this->app->instance(ModelProviderInterface::class, $mock);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/ai/faq_lookup', ['message' => 'Mấy giờ nhận phòng?']);

        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('response_class', $data);
        $this->assertArrayNotHasKey('canary', $data);
    }
}
