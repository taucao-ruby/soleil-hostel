<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates query parameters for location detail with room availability.
 *
 * Extracted from LocationController::show() (M-04).
 */
class ShowLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'check_in' => 'nullable|date|required_with:check_out',
            'check_out' => 'nullable|date|after:check_in',
            'guests' => 'nullable|integer|min:1',
        ];
    }
}
