<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * RegisterTest — POST /api/auth/register
 *
 * Coverage:
 * 1. Happy path: 201 + user persisted + password hashed
 * 2. Missing required fields → 422
 * 3. Password mismatch (password_confirmation wrong) → 422
 * 4. Duplicate email → 422 (not 500)
 * 5. Mail transport failure does NOT cause 500
 */
class RegisterTest extends TestCase
{
    private const ENDPOINT = '/api/auth/register';

    private const VALID_PAYLOAD = [
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'Password1!',
        'password_confirmation' => 'Password1!',
    ];

    // =========================================================
    // 1. Happy path
    // =========================================================

    public function test_happy_path_returns_201_and_creates_user(): void
    {
        $response = $this->postJson(self::ENDPOINT, self::VALID_PAYLOAD);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                ],
            ])
            ->assertJsonFragment(['email' => 'jane@example.com']);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_happy_path_password_is_hashed_in_database(): void
    {
        $this->postJson(self::ENDPOINT, self::VALID_PAYLOAD)->assertStatus(201);

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertNotEquals('Password1!', $user->password);
        $this->assertTrue(Hash::check('Password1!', $user->password));
    }

    // =========================================================
    // 2. Missing required fields → 422
    // =========================================================

    /** @dataProvider missingFieldProvider */
    public function test_missing_required_field_returns_422(string $field): void
    {
        $payload = array_filter(
            self::VALID_PAYLOAD,
            fn ($key) => $key !== $field,
            ARRAY_FILTER_USE_KEY
        );

        $this->postJson(self::ENDPOINT, $payload)->assertStatus(422);
    }

    public static function missingFieldProvider(): array
    {
        return [
            'missing name'     => ['name'],
            'missing email'    => ['email'],
            'missing password' => ['password'],
        ];
    }

    // =========================================================
    // 3. Password confirmation mismatch → 422
    // =========================================================

    public function test_password_confirmation_mismatch_returns_422(): void
    {
        $payload = array_merge(self::VALID_PAYLOAD, ['password_confirmation' => 'WrongPassword!']);

        $this->postJson(self::ENDPOINT, $payload)->assertStatus(422);
    }

    // =========================================================
    // 4. Duplicate email → 422 (not 500)
    // =========================================================

    public function test_duplicate_email_returns_422_not_500(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson(self::ENDPOINT, self::VALID_PAYLOAD);

        $response->assertStatus(422)
            ->assertJsonValidationErrorFor('email');
    }

    // =========================================================
    // 5. Mail transport failure does NOT cause 500
    // =========================================================

    public function test_smtp_failure_during_verification_email_does_not_cause_500(): void
    {
        // Replace the listener in the IoC container with a mock that throws.
        // This simulates a real SMTP / mail transport failure at the listener level.
        $this->instance(
            SendEmailVerificationNotification::class,
            \Mockery::mock(SendEmailVerificationNotification::class, function ($mock) {
                $mock->shouldReceive('handle')
                    ->once()
                    ->andThrow(new \RuntimeException('SMTP connection refused'));
            })
        );

        $response = $this->postJson(self::ENDPOINT, self::VALID_PAYLOAD);

        // Registration must still succeed — email failure is non-fatal.
        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }
}
