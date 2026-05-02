<?php

declare(strict_types=1);

namespace Tests\Feature\AiHarness;

use App\AiHarness\Providers\ModelProviderInterface;
use App\AiHarness\Providers\RawModelResponse;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PiiBlockTest extends TestCase
{
    private const AUDIT_KEY = 'pii-block-test-secret-key-123456';

    public function test_pii_model_output_returns_safe_response_and_hash_only_audit_log(): void
    {
        $this->configureAiLog();
        config()->set('app.key', self::AUDIT_KEY);
        config()->set('ai_harness.enabled', true);
        config()->set('ai_harness.canary.faq_lookup_percentage', 100);

        $rawOutput = 'Guest fixture PII: alice.secret@example.com, +84912345678, passport A1234567.';
        $this->mockProvider(new RawModelResponse(
            providerName: 'anthropic',
            rawContent: $rawOutput,
            promptTokens: 100,
            completionTokens: 50,
            latencyMs: 200,
        ));

        $response = $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/ai/faq_lookup', ['message' => 'What is the check-in policy?']);

        $response->assertOk();
        $response->assertJsonPath('data.response_class', 'refusal');
        $response->assertJsonPath(
            'data.content',
            'I cannot share personal information. Please contact the front desk directly.',
        );

        $responseBody = $response->getContent();
        $this->assertStringNotContainsString('alice.secret@example.com', $responseBody);
        $this->assertStringNotContainsString('+84912345678', $responseBody);
        $this->assertStringNotContainsString('A1234567', $responseBody);

        $this->assertFileExists($this->auditLogPath());

        $log = (string) file_get_contents($this->auditLogPath());
        $expectedHash = hash_hmac('sha256', $rawOutput, self::AUDIT_KEY);

        $this->assertStringContainsString($expectedHash, $log);
        $this->assertStringNotContainsString('alice.secret@example.com', $log);
        $this->assertStringNotContainsString('+84912345678', $log);
        $this->assertStringNotContainsString('A1234567', $log);
    }

    private function configureAiLog(): void
    {
        $path = $this->auditLogPath();

        if (file_exists($path)) {
            unlink($path);
        }

        config()->set('logging.channels.ai', [
            'driver' => 'single',
            'path' => $path,
            'level' => 'debug',
        ]);

        Log::forgetChannel('ai');
    }

    private function auditLogPath(): string
    {
        return storage_path('logs/testing-ai-pii-block.log');
    }

    private function mockProvider(RawModelResponse $response): void
    {
        $mock = $this->createMock(ModelProviderInterface::class);
        $mock->method('complete')->willReturn($response);
        $mock->method('isAvailable')->willReturn(true);
        $mock->method('getProviderName')->willReturn('anthropic');

        $this->app->instance(ModelProviderInterface::class, $mock);
    }
}
