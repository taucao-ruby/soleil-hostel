<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\AddCorrelationId;
use Tests\TestCase;

/**
 * OBS-001 — Correlation ID middleware contract.
 *
 * Acceptance criteria:
 *  - Server-generated UUID is always different from any client-supplied value.
 *  - Client-supplied IDs over 64 chars or with special characters are silently
 *    rejected (null stored).
 *  - Inject arbitrary ID → response header uses server-generated UUID, not the
 *    injected value.
 */
class CorrelationIdTest extends TestCase
{
    private const UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private const PUBLIC_LIVENESS = '/api/health/live';

    public function test_server_correlation_id_is_uuid_when_no_client_header(): void
    {
        $response = $this->getJson(self::PUBLIC_LIVENESS);

        $serverId = $response->headers->get(AddCorrelationId::HEADER_NAME);

        $this->assertNotNull($serverId);
        $this->assertMatchesRegularExpression(self::UUID_REGEX, $serverId);
        $this->assertNull(
            $response->headers->get(AddCorrelationId::CLIENT_HEADER_NAME),
            'X-Client-Correlation-ID must be absent when no client header was sent'
        );
    }

    public function test_arbitrary_injected_correlation_id_does_not_become_server_id(): void
    {
        $injected = 'attacker-injected-trace-id';

        $response = $this->withHeader(AddCorrelationId::HEADER_NAME, $injected)
            ->getJson(self::PUBLIC_LIVENESS);

        $serverId = $response->headers->get(AddCorrelationId::HEADER_NAME);

        $this->assertNotNull($serverId);
        $this->assertNotSame(
            $injected,
            $serverId,
            'Server response header MUST be the server-generated UUID, never the client value'
        );
        $this->assertMatchesRegularExpression(self::UUID_REGEX, $serverId);
    }

    public function test_valid_client_correlation_id_is_echoed_in_separate_header(): void
    {
        // Validated by /^[a-zA-Z0-9\-]{8,64}$/.
        $client = 'client-trace-abc-123';

        $response = $this->withHeader(AddCorrelationId::HEADER_NAME, $client)
            ->getJson(self::PUBLIC_LIVENESS);

        $response->assertHeader(AddCorrelationId::CLIENT_HEADER_NAME, $client);

        $serverId = $response->headers->get(AddCorrelationId::HEADER_NAME);
        $this->assertMatchesRegularExpression(self::UUID_REGEX, $serverId);
        $this->assertNotSame($client, $serverId);
    }

    public function test_each_request_gets_a_distinct_server_correlation_id(): void
    {
        $first = $this->getJson(self::PUBLIC_LIVENESS)->headers->get(AddCorrelationId::HEADER_NAME);
        $second = $this->getJson(self::PUBLIC_LIVENESS)->headers->get(AddCorrelationId::HEADER_NAME);

        $this->assertNotSame($first, $second);
    }

    /**
     * @dataProvider invalidClientCorrelationIds
     */
    public function test_invalid_client_correlation_ids_are_silently_rejected(string $invalid): void
    {
        $response = $this->withHeader(AddCorrelationId::HEADER_NAME, $invalid)
            ->getJson(self::PUBLIC_LIVENESS);

        // No echo header — invalid values are dropped, not surfaced.
        $this->assertNull(
            $response->headers->get(AddCorrelationId::CLIENT_HEADER_NAME),
            "Invalid client ID '{$invalid}' must be silently rejected"
        );

        $serverId = $response->headers->get(AddCorrelationId::HEADER_NAME);
        $this->assertMatchesRegularExpression(self::UUID_REGEX, $serverId);
        $this->assertNotSame($invalid, $serverId);
    }

    public static function invalidClientCorrelationIds(): array
    {
        return [
            'too short (<8)' => ['short12'],
            'too long (>64)' => [str_repeat('a', 65)],
            'contains space' => ['client trace 123'],
            'contains slash' => ['client/trace/123'],
            'contains semicolon' => ['client;DROP TABLE users'],
            'contains newline' => ["client\ntrace\n123"],
            'contains crlf injection' => ["abc12345\r\nX-Inject: bad"],
            'contains angle brackets' => ['<script>abc</script>'],
            'contains null byte' => ["abc12345\0evil"],
            'contains unicode' => ['client-trace-éééé'],
        ];
    }

    public function test_validated_client_id_at_max_length_is_accepted(): void
    {
        $client = str_repeat('a', 64);

        $response = $this->withHeader(AddCorrelationId::HEADER_NAME, $client)
            ->getJson(self::PUBLIC_LIVENESS);

        $response->assertHeader(AddCorrelationId::CLIENT_HEADER_NAME, $client);
    }

    public function test_validated_client_id_at_min_length_is_accepted(): void
    {
        $client = 'a-b-c1d2';  // 8 chars

        $response = $this->withHeader(AddCorrelationId::HEADER_NAME, $client)
            ->getJson(self::PUBLIC_LIVENESS);

        $response->assertHeader(AddCorrelationId::CLIENT_HEADER_NAME, $client);
    }

    public function test_empty_client_header_is_silently_ignored(): void
    {
        $response = $this->withHeader(AddCorrelationId::HEADER_NAME, '')
            ->getJson(self::PUBLIC_LIVENESS);

        $this->assertNull($response->headers->get(AddCorrelationId::CLIENT_HEADER_NAME));
        $this->assertMatchesRegularExpression(
            self::UUID_REGEX,
            $response->headers->get(AddCorrelationId::HEADER_NAME)
        );
    }
}
