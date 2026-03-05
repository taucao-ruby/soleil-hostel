<?php

namespace Tests\Unit\Requests\Auth;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Validates Auth\LoginRequest rules — specifically that
 * password enforces min:8 (M-07 fix).
 */
class LoginRequestValidationTest extends TestCase
{
    private function rules(): array
    {
        return (new LoginRequest)->rules();
    }

    public function test_password_requires_minimum_8_characters(): void
    {
        $validator = Validator::make(
            ['email' => 'user@example.com', 'password' => 'short'],
            $this->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_password_passes_with_8_characters(): void
    {
        $validator = Validator::make(
            ['email' => 'user@example.com', 'password' => 'longpass'],
            $this->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_password_is_required(): void
    {
        $validator = Validator::make(
            ['email' => 'user@example.com'],
            $this->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_email_is_required(): void
    {
        $validator = Validator::make(
            ['password' => 'longpassword'],
            $this->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }
}
