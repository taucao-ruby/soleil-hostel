<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Http\Requests\RoomRequest;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class OpenApiEnumContractTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private ?array $openApi = null;

    public function test_room_status_enum_matches_runtime_contract(): void
    {
        $expected = $this->roomStatusValuesFromValidation();

        $this->assertOpenApiEnumMatches($expected, [
            'components',
            'schemas',
            'Room',
            'properties',
            'status',
            'enum',
        ], 'components.schemas.Room.properties.status');

        $this->assertOpenApiEnumMatches($expected, [
            'paths',
            '/v1/rooms',
            'post',
            'requestBody',
            'content',
            'application/json',
            'schema',
            'properties',
            'status',
            'enum',
        ], 'paths./v1/rooms.post.requestBody.content.application/json.schema.properties.status');

        $this->assertOpenApiEnumMatches($expected, [
            'paths',
            '/v1/rooms/{id}',
            'put',
            'requestBody',
            'content',
            'application/json',
            'schema',
            'properties',
            'status',
            'enum',
        ], 'paths./v1/rooms/{id}.put.requestBody.content.application/json.schema.properties.status');

        $this->assertOpenApiStringIsAllowed($expected, [
            'components',
            'schemas',
            'Room',
            'properties',
            'status',
            'example',
        ], 'components.schemas.Room.properties.status.example');

        $this->assertOpenApiStringIsAllowed($expected, [
            'paths',
            '/v1/rooms',
            'post',
            'requestBody',
            'content',
            'application/json',
            'schema',
            'properties',
            'status',
            'default',
        ], 'paths./v1/rooms.post.requestBody.content.application/json.schema.properties.status.default');
    }

    public function test_refund_status_enum_matches_runtime_contract(): void
    {
        $this->assertOpenApiEnumMatches($this->refundStatusValuesFromStorageContract(), [
            'components',
            'schemas',
            'Booking',
            'properties',
            'refund_status',
            'enum',
        ], 'components.schemas.Booking.properties.refund_status');
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $path
     */
    private function assertOpenApiEnumMatches(array $expected, array $path, string $label): void
    {
        $actual = $this->openApiEnum($path, $label);

        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);

        $this->assertSame(
            $expected,
            $actual,
            "{$label} enum must match backend runtime contract values exactly."
        );
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $path
     */
    private function assertOpenApiStringIsAllowed(array $expected, array $path, string $label): void
    {
        $actual = $this->openApiValue($path, $label);

        $this->assertIsString($actual, "{$label} must be a string.");
        $this->assertContains($actual, $expected, "{$label} must be one of the backend runtime contract values.");
    }

    /**
     * @param  list<string>  $path
     * @return list<string>
     */
    private function openApiEnum(array $path, string $label): array
    {
        $enum = $this->openApiValue($path, $label);

        $this->assertIsArray($enum, "{$label} enum is missing from OpenAPI contract.");

        return array_map(
            static fn (mixed $value): string => (string) $value,
            array_values($enum)
        );
    }

    /**
     * @param  list<string>  $path
     */
    private function openApiValue(array $path, string $label): mixed
    {
        $value = $this->openApi();

        foreach ($path as $segment) {
            $this->assertIsArray($value, "{$label} parent path is missing from OpenAPI contract.");
            $this->assertArrayHasKey($segment, $value, "{$label} is missing from OpenAPI contract.");

            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function openApi(): array
    {
        if ($this->openApi === null) {
            $openApi = Yaml::parseFile(base_path('../docs/api/openapi.yaml'));

            $this->assertIsArray($openApi, 'OpenAPI contract must parse to an array.');

            $this->openApi = $openApi;
        }

        return $this->openApi;
    }

    /** @return list<string> */
    private function roomStatusValuesFromValidation(): array
    {
        $rules = (new RoomRequest)->rules();
        $statusRule = $rules['status'] ?? null;

        $this->assertIsString($statusRule, 'RoomRequest status validation rule is missing.');

        return $this->valuesFromInRule($statusRule, 'RoomRequest.status');
    }

    /** @return list<string> */
    private function refundStatusValuesFromStorageContract(): array
    {
        $migration = file_get_contents(base_path('database/migrations/2026_01_11_000001_add_payment_fields_to_bookings.php'));

        $this->assertIsString($migration, 'Refund status migration must be readable.');

        $matched = preg_match(
            "/\\\$table->string\\('refund_status'\\).*?->comment\\('([^']+)'\\)/s",
            $migration,
            $matches
        );

        $this->assertSame(1, $matched, 'bookings.refund_status storage contract comment is missing.');

        return array_values(array_filter(explode('|', $matches[1]), 'strlen'));
    }

    /** @return list<string> */
    private function valuesFromInRule(string $rule, string $label): array
    {
        foreach (explode('|', $rule) as $part) {
            if (str_starts_with($part, 'in:')) {
                return array_values(array_filter(explode(',', substr($part, 3)), 'strlen'));
            }
        }

        $this->fail("{$label} must use an in: validation rule for contract comparison.");
    }
}
