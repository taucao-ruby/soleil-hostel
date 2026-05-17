<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query validation for the admin contact message index.
 */
class ContactIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC remains enforced by route middleware and ContactMessagePolicy.
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        $validated = $this->validated();

        return (int) ($validated['per_page'] ?? 15);
    }
}
