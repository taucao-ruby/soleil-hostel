<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query-filter validation for GET /api/v1/admin/bookings (AdminBookingController::index).
 *
 * Hardening for F-7: the controller previously read filters straight from
 * $request->query() with no allowlist or bounds. This request allowlists
 * status against the BookingStatus enum, pins date filters to Y-m-d, bounds
 * search length, and caps per_page so a caller cannot amplify query/page load.
 *
 * @see \App\Http\Controllers\AdminBookingController::index()
 */
class AdminBookingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC is enforced by the route middleware (role:moderator) and the
        // Gate::authorize('view-all-bookings') check kept inside the controller.
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'check_in_start' => ['sometimes', 'date_format:Y-m-d'],
            'check_in_end' => ['sometimes', 'date_format:Y-m-d'],
            'check_out_start' => ['sometimes', 'date_format:Y-m-d'],
            'check_out_end' => ['sometimes', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'string', Rule::enum(BookingStatus::class)],
            'location_id' => ['sometimes', 'integer', 'exists:locations,id'],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];

        // Range ordering is only meaningful when both ends are supplied. Adding
        // the cross-field rule unconditionally would misfire when a caller
        // passes a single open-ended bound (e.g. check_in_end with no start).
        if ($this->has('check_in_start') && $this->has('check_in_end')) {
            $rules['check_in_end'][] = 'after_or_equal:check_in_start';
        }

        if ($this->has('check_out_start') && $this->has('check_out_end')) {
            $rules['check_out_end'][] = 'after_or_equal:check_out_start';
        }

        return $rules;
    }
}
