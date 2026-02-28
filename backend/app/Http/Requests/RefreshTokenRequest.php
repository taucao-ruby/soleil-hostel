<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RefreshTokenRequest - Request validation for POST /api/auth/refresh
 *
 * Rules:
 * - token: required, string (current token)
 *
 * Flow:
 * 1. Extract token from the Authorization header
 * 2. Validate: token exists, not expired, not revoked
 * 3. Create new token (same type as the original)
 * 4. Revoke old token
 * 5. Return new token
 *
 * IMPORTANT: A new token is NOT issued if the current token is:
 * - Expired
 * - Revoked
 *
 * On 401 response → frontend must re-authenticate
 */
class RefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Token validation is handled by middleware; authorize() is always true here
        return true;
    }

    public function rules(): array
    {
        return [
            // Token: not a form field; validated by the Authorization header in middleware
            // Middleware validates the Authorization header, not a form field
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Token không được để trống.',
        ];
    }
}
