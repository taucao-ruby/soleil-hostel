<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RefreshTokenRequest - Validation cho POST /api/auth/refresh
 *
 * Rules:
 * - token: required, string (current token)
 *
 * Flow:
 * 1. Extract token từ Authorization header
 * 2. Validate: token tồn tại, chưa expire, chưa revoke
 * 3. Tạo token mới (cùng loại)
 * 4. Revoke token cũ
 * 5. Return token mới
 *
 * IMPORTANT: Không cấp token mới nếu token cũ đã:
 * - Hết hạn (expired)
 * - Bị revoke
 *
 * Nếu cập nhập 401 → frontend phải login lại
 */
class RefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Token validation bằng middleware, không cần authorize() ở đây
        return true;
    }

    public function rules(): array
    {
        return [
            // Token: optional ở form, nhưng REQUIRED ở Authorization header
            // Middleware sẽ check Authorization header, không form field
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Token không được để trống.',
        ];
    }
}
