<?php

namespace Tests\Feature\RateLimiting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_login_rate_limit_5_per_minute_per_ip(): void
    {
        $ip = '203.0.113.51';

        // Attempt 5 logins within 1 minute (should succeed)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post('/api/auth/login-httponly', [
                    'email' => 'test@example.com',
                    'password' => 'password',
                ]);

            // First 5 should return 401 (wrong credentials) not 429 (rate limited)
            $this->assertNotEquals(429, $response->status());
        }

        // 6th attempt should be rate limited (429 Too Many Requests)
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/api/auth/login-httponly', [
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

        $this->assertEquals(429, $response->status());
        // Rate limit response might be HTML or JSON, just verify it's 429
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_login_rate_limit_20_per_minute_per_ip_across_emails(): void
    {
        $ip = '203.0.113.52';

        // Attempt 20 logins for different emails from one IP (should not hit the email+IP cap)
        for ($i = 0; $i < 20; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post('/api/auth/login-httponly', [
                    'email' => "daily{$i}@example.com",
                    'password' => 'password',
                ]);

            $this->assertNotEquals(429, $response->status());
        }

        // 21st attempt should be rate limited by the IP cap.
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/api/auth/login-httponly', [
                'email' => 'daily20@example.com',
                'password' => 'password',
            ]);

        $this->assertEquals(429, $response->status());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_different_emails_have_separate_limits(): void
    {
        $ip = '203.0.113.53';

        // Make 5 requests with email1 (hits the email+IP limit)
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post('/api/auth/login-httponly', [
                    'email' => 'email1@example.com',
                    'password' => 'password',
                ]);
        }

        // A different email from the same IP has a separate email+IP bucket.
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/api/auth/login-httponly', [
                'email' => 'email2@example.com',
                'password' => 'password',
            ]);

        $this->assertNotEquals(429, $response->status());
    }
}
