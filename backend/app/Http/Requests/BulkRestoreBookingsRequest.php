<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates bulk restore request for admin booking management.
 *
 * Extracted from AdminBookingController::restoreBulk() (M-02).
 */
class BulkRestoreBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by route middleware (role:admin)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:bookings,id',
        ];
    }
}
