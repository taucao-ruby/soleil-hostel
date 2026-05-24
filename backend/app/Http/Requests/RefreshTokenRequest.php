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
 * 1. Apply route-level throttle before token lookup
 * 2. Extract token from the Authorization header
 * 3. Validate: token exists, not expired, not revoked
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
        // Token validation is handled by the refresh controller after route throttling.
        return true;
    }

    public function rules(): array
    {
        return [
            // Token: not a form field; validated from the Authorization header.
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Token không được để trống.',
        ];
    }
}
