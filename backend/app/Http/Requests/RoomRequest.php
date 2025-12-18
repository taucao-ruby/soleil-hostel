<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for Room create/update operations.
 *
 * For updates, includes optional lock_version field for optimistic locking.
 * The lock_version should be the version the client received when reading
 * the room data. If omitted, backward compatible mode is used.
 */
class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:100',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'max_guests' => 'required|integer|min:1',
            'status' => 'required|in:available,booked,maintenance',
        ];

        // For PUT/PATCH requests (updates), add optional lock_version validation
        // lock_version is optional for backward compatibility, but recommended
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['lock_version'] = 'sometimes|nullable|integer|min:1';
        }

        return $rules;
    }

    /**
     * Get the validated lock_version, or null if not provided.
     *
     * Helper method for controller to easily extract the version.
     */
    public function getLockVersion(): ?int
    {
        $version = $this->validated('lock_version');
        
        return $version !== null ? (int) $version : null;
    }

    /**
     * Custom error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'lock_version.integer' => 'The lock version must be a valid integer.',
            'lock_version.min' => 'The lock version must be at least 1.',
        ];
    }
}
