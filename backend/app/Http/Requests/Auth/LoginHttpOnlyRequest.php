<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginHttpOnlyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
            'remember_me' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không hợp lệ.',
            'email.max' => 'Email không được quá 255 ký tự.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải ít nhất 8 ký tự.',
            'password.max' => 'Mật khẩu không được quá 72 ký tự.',
        ];
    }

    public function getEmail(): string
    {
        return strtolower(trim((string) $this->input('email')));
    }

    public function getPassword(): string
    {
        return (string) $this->input('password');
    }

    public function shouldRemember(): bool
    {
        return $this->boolean('remember_me', false);
    }
}
