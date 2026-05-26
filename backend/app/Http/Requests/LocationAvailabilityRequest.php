<?php

namespace App\Http\Requests;

use App\Support\HostelClock;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

/**
 * Validates query parameters for location availability check.
 *
 * Extracted from LocationController::availability() (M-04).
 */
class LocationAvailabilityRequest extends FormRequest
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
                'required',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        if (HostelClock::isDateBeforeToday((string) $value)) {
                            $fail('The check-in date must be today or later.');
                        }
                    } catch (InvalidArgumentException) {
                        // date_format reports invalid input; avoid replacing that error.
                    }
                },
            ],
            'check_out' => 'required|date_format:Y-m-d|after:check_in',
            'guests' => 'nullable|integer|min:1',
        ];
    }
}
