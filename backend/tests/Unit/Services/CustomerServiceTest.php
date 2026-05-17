<?php

namespace Tests\Unit\Services;

use App\Services\CustomerService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
{
    public function test_customer_profile_cache_key_uses_safe_hash_shape_without_raw_email_or_reserved_characters(): void
    {
        $email = ' User:{Admin}:Profile@example.com ';

        $key = $this->captureCustomerProfileCacheKeys($email)[0];
        $variablePortion = substr($key, strlen('customer_profile_'));

        $this->assertSame(
            'customer_profile_'.hash('sha256', strtolower(trim($email))),
            $key
        );
        $this->assertMatchesRegularExpression('/^customer_profile_[a-f0-9]{64}$/', $key);
        $this->assertStringNotContainsString(trim($email), $key);
        $this->assertStringNotContainsString(strtolower(trim($email)), $key);
        $this->assertStringNotContainsString('@', $variablePortion);
        $this->assertStringNotContainsString(':', $variablePortion);
        $this->assertStringNotContainsString('{', $variablePortion);
        $this->assertStringNotContainsString('}', $variablePortion);
    }

    public function test_customer_profile_cache_key_is_case_insensitive(): void
    {
        [$uppercaseKey, $lowercaseKey] = $this->captureCustomerProfileCacheKeys(
            'USER@example.com',
            'user@example.com'
        );

        $this->assertSame($lowercaseKey, $uppercaseKey);
    }

    public function test_customer_profile_cache_key_trims_boundary_whitespace(): void
    {
        [$spacedKey, $trimmedKey] = $this->captureCustomerProfileCacheKeys(
            ' user@example.com ',
            'user@example.com'
        );

        $this->assertSame($trimmedKey, $spacedKey);
    }

    /**
     * @return list<string>
     */
    private function captureCustomerProfileCacheKeys(string ...$emails): array
    {
        $keys = [];

        Cache::shouldReceive('remember')
            ->times(count($emails))
            ->withArgs(function (string $key, int $ttl, callable $callback) use (&$keys): bool {
                $keys[] = $key;

                return $ttl === 300;
            })
            ->andReturn((object) [
                'email' => 'cached@example.com',
            ]);

        $service = new CustomerService;

        foreach ($emails as $email) {
            $service->getCustomerProfile($email);
        }

        return $keys;
    }
}
