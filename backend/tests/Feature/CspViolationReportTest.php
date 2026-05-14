<?php

namespace Tests\Feature;

use App\Http\Middleware\LogPerformance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CspViolationReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(LogPerformance::class);
    }

    public function test_valid_report_is_logged_with_bounded_structured_context(): void
    {
        $logger = $this->captureLogs();

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/csp-violation-report', [
                'csp-report' => [
                    'document-uri' => 'https://soleil.test/booking',
                    'blocked-uri' => 'https://cdn.soleil.test/app.js',
                    'effective-directive' => 'script-src',
                    'violated-directive' => 'script-src-elem',
                    'disposition' => 'enforce',
                    'status-code' => '200',
                ],
            ]);

        $response->assertStatus(204);

        $context = $this->singleCspReportContext($logger);
        $this->assertSame('https://soleil.test/booking', $context['document_uri']);
        $this->assertSame('https://cdn.soleil.test/app.js', $context['blocked_uri']);
        $this->assertSame('script-src', $context['effective_directive']);
        $this->assertSame('script-src-elem', $context['violated_directive']);
        $this->assertSame(200, $context['status_code']);
    }

    public function test_csp_violation_report_rate_limiter_rejects_61st_request_per_ip(): void
    {
        $ip = '203.0.113.20';
        $this->clearRateLimitKeysFor($ip);

        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $this
                ->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/csp-violation-report', $this->validPayload())
                ->assertStatus(204);
        }

        $this
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/csp-violation-report', $this->validPayload())
            ->assertStatus(429);

        $this->clearRateLimitKeysFor($ip);
    }

    public function test_oversized_payload_is_rejected_before_logging(): void
    {
        $logger = $this->captureLogs();

        $body = json_encode([
            'csp-report' => [
                'document-uri' => 'https://soleil.test/'.str_repeat('a', 4096),
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/csp-violation-report',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/csp-report',
                'CONTENT_LENGTH' => (string) strlen($body),
                'REMOTE_ADDR' => '203.0.113.30',
            ],
            $body,
        );

        $response->assertStatus(413);
        $this->assertSame([], $this->cspReportContexts($logger));
    }

    public function test_log_injection_characters_are_removed_from_logged_fields(): void
    {
        $logger = $this->captureLogs();

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.40'])
            ->postJson('/api/csp-violation-report', [
                'csp-report' => [
                    'document-uri' => "https://soleil.test\r\nFAKE_LOG=1",
                    'blocked-uri' => "inline\nforged",
                    'violated-directive' => 'script-src',
                ],
            ]);

        $response->assertStatus(204);

        $context = $this->singleCspReportContext($logger);
        $this->assertStringNotContainsString("\r", $context['document_uri']);
        $this->assertStringNotContainsString("\n", $context['document_uri']);
        $this->assertStringNotContainsString("\r", $context['blocked_uri']);
        $this->assertStringNotContainsString("\n", $context['blocked_uri']);
        $this->assertSame('https://soleil.test FAKE_LOG=1', $context['document_uri']);
        $this->assertSame('inline forged', $context['blocked_uri']);
    }

    public function test_unknown_fields_are_not_logged(): void
    {
        $logger = $this->captureLogs();

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->postJson('/api/csp-violation-report', [
                'csp-report' => [
                    'document-uri' => 'https://soleil.test/',
                    'blocked-uri' => 'inline',
                    'violated-directive' => 'style-src',
                    'source-file' => str_repeat('x', 512),
                    'line-number' => 42,
                    'nested-noise' => ['payload' => str_repeat('y', 512)],
                    'original-policy' => str_repeat('z', 512),
                ],
            ]);

        $response->assertStatus(204);

        $context = $this->singleCspReportContext($logger);
        $this->assertArrayNotHasKey('source-file', $context);
        $this->assertArrayNotHasKey('line-number', $context);
        $this->assertArrayNotHasKey('nested-noise', $context);
        $this->assertArrayNotHasKey('original_policy', $context);
    }

    private function validPayload(): array
    {
        return [
            'csp-report' => [
                'document-uri' => 'https://soleil.test/',
                'blocked-uri' => 'inline',
                'violated-directive' => 'script-src',
                'status-code' => 200,
            ],
        ];
    }

    private function clearRateLimitKeysFor(string $ip): void
    {
        RateLimiter::clear('csp-report:minute:'.$ip);
        RateLimiter::clear('csp-report:hour:'.$ip);
    }

    private function captureLogs(): object
    {
        $logger = new class
        {
            public array $warnings = [];

            public function channel(string $name): self
            {
                return $this;
            }

            public function shareContext(array $context): void {}

            public function withContext(array $context): void {}

            public function warning(string $message, array $context = []): void
            {
                $this->warnings[] = [
                    'message' => $message,
                    'context' => $context,
                ];
            }

            public function error(string $message, array $context = []): void {}

            public function info(string $message, array $context = []): void {}

            public function debug(string $message, array $context = []): void {}

            public function notice(string $message, array $context = []): void {}

            public function critical(string $message, array $context = []): void {}

            public function alert(string $message, array $context = []): void {}

            public function emergency(string $message, array $context = []): void {}

            public function log(string $level, string $message, array $context = []): void {}
        };

        Log::swap($logger);

        return $logger;
    }

    private function singleCspReportContext(object $logger): array
    {
        $contexts = $this->cspReportContexts($logger);

        $this->assertCount(1, $contexts);

        return $contexts[0];
    }

    private function cspReportContexts(object $logger): array
    {
        return array_values(array_map(
            fn (array $entry): array => $entry['context'],
            array_filter(
                $logger->warnings,
                fn (array $entry): bool => $entry['message'] === 'CSP Violation Report',
            ),
        ));
    }
}
