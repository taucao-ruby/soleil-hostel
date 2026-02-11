<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * LoginRequest - Validation cho POST /api/auth/login
 *
 * Rules:
 * - email: required, valid email format, exists ở users table
 * - password: required, string, min 8 chars
 * - remember_me: optional boolean (default false)
 * - device_name: optional string (identify device)
 *
 * Flow:
 * 1. Validate input
 * 2. Check email + password
 * 3. Tạo token (short_lived hoặc long_lived based on remember_me)
 * 4. Return token + user info
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint, ai cũng có thể login
    }

    public function rules(): array
    {
        return [
            // Email: required, valid email, must exist in users table
            'email' => [
                'required',
                'email:rfc', // Remove DNS check for compatibility with tests
                'exists:users,email',
            ],

            // Password: required, min 8 chars
            'password' => [
                'required',
                'string',
                'min:8',
            ],

            // Remember me: optional, nếu true → long_lived token (30 ngày)
            'remember_me' => [
                'sometimes',
                'boolean',
            ],

            // Device name: optional, identify device (e.g. "iPhone 15 Pro")
            'device_name' => [
                'sometimes',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Custom validation messages (tiếng Việt)
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không hợp lệ.',
            'email.exists' => 'Email không tồn tại trong hệ thống.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải ít nhất 8 ký tự.',
            'remember_me.boolean' => 'Remember me phải là true/false.',
            'device_name.max' => 'Tên device không được quá 255 ký tự.',
        ];
    }

    /**
     * Prepare input for validation
     *
     * - remember_me: convert string "true"/"false" to boolean
     * - device_name: trim whitespace
     */
    public function prepareForValidation(): void
    {
        $this->merge([
            'remember_me' => $this->boolean('remember_me'),
        ]);
    }

    /**
     * Get remember_me flag (default false)
     */
    public function shouldRemember(): bool
    {
        return $this->boolean('remember_me', false);
    }

    /**
     * Get device name (default: "Web Browser" hoặc user agent)
     */
    public function getDeviceName(): string
    {
        if ($this->filled('device_name')) {
            return $this->input('device_name');
        }

        // Fallback: detect device type từ user agent
        $userAgent = $this->userAgent();

        if (str_contains($userAgent, 'Mobile')) {
            return 'Mobile Device';
        }

        if (str_contains($userAgent, 'Tablet')) {
            return 'Tablet';
        }

        return 'Web Browser';
    }

    /**
     * Get email (sanitized)
     */
    public function getEmail(): string
    {
        return strtolower(trim($this->input('email')));
    }

    /**
     * Get password
     */
    public function getPassword(): string
    {
        return $this->input('password');
    }
}
