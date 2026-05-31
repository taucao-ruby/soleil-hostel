<?php

namespace App\Http\Requests;

use App\Support\HostelClock;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

/**
 * Request validation for listing/filtering rooms.
 *
 * Extracted from RoomController::index() inline validation
 * to maintain consistency with other room endpoints that use FormRequest.
 *
 * @see \App\Http\Controllers\RoomController::index()
 */
class ListRoomsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'location_id' => 'nullable|integer|exists:locations,id',
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
        ];
    }

    public function messages(): array
    {
        return [
            'check_in.required_with' => 'Check-in date is required when check-out is provided.',
            'check_out.after' => 'Check-out date must be after check-in date.',
            'location_id.exists' => 'The selected location does not exist.',
        ];
    }
}
