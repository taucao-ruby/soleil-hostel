<?php

namespace App\Http\Requests;

use App\Support\HostelClock;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

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
            'check_in' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:check_out',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    try {
                        if (HostelClock::isDateBeforeToday((string) $value)) {
                            $fail('The check-in date must be today or later.');
                        }
                    } catch (InvalidArgumentException) {
                        // date_format reports invalid input; avoid replacing that error.
                    }
                },
            ],
            'check_out' => 'nullable|date_format:Y-m-d|after:check_in',
            'guests' => 'nullable|integer|min:1',
        ];
    }
}
