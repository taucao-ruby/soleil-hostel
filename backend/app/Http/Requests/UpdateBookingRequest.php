<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Models\User;
use App\Support\HostelClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class UpdateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // SH-01: date edits on a money-final (confirmed or paid) booking are
        // prohibited in Phase 0 — guests must cancel + rebook. Mirrors the
        // number_of_guests => prohibited rationale: this endpoint never re-prices
        // a captured payment. This is PUT-like (check_in/check_out are always
        // resent), so we only prohibit an *actual* change, never an unchanged
        // resend (so guest contact info on a confirmed booking stays editable).
        //
        // Gated on the requester actually being authorized to update this booking
        // ($user->can('update', ...)). Validation runs before the controller's
        // authorize('update'); without this gate a non-owner (or lifecycle-blocked
        // actor) would get a 422 leaking the booking's payment state instead of a
        // clean 403. Authorization answers first; the money-final rule only
        // constrains someone who could otherwise edit.
        $bound = $this->route('booking');
        $booking = $bound instanceof Booking ? $bound : null;
        $user = $this->user();
        $moneyFinal = $booking !== null
            && $user instanceof User
            && $user->can('update', $booking)
            && $booking->isMoneyFinal();

        return [
            'room_id' => 'prohibited',
            'number_of_guests' => 'prohibited',
            'check_in' => [
                'required',
                'date_format:Y-m-d',
                Rule::prohibitedIf(fn (): bool => $moneyFinal && $this->dateFieldChanged($booking, 'check_in')),
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
            'check_out' => [
                'required',
                'date_format:Y-m-d',
                'after:check_in',
                Rule::prohibitedIf(fn (): bool => $moneyFinal && $this->dateFieldChanged($booking, 'check_out')),
            ],
            'guest_name' => 'required|string|min:2|max:255',
            'guest_email' => 'required|email|max:255',
            'special_requests' => 'nullable|string|max:2000',
        ];
    }

    /**
     * True when the request submits a date for $field that differs from the
     * booking's persisted value. Returns false when there is no bound booking
     * (e.g. unit tests calling rules() without a route) or the field is absent.
     */
    private function dateFieldChanged(?Booking $booking, string $field): bool
    {
        if (! $booking instanceof Booking || ! $this->has($field)) {
            return false;
        }

        $submitted = $this->input($field);
        if (! is_string($submitted)) {
            return false;
        }

        return $submitted !== $booking->{$field}?->toDateString();
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'room_id.prohibited' => 'Room changes require a dedicated room-change flow.',
            'number_of_guests.prohibited' => 'Guest count changes are not supported by this update endpoint.',
            'check_in.prohibited' => 'Không thể đổi ngày nhận phòng cho booking đã thanh toán hoặc đã xác nhận. Vui lòng hủy và đặt lại.',
            'check_out.prohibited' => 'Không thể đổi ngày trả phòng cho booking đã thanh toán hoặc đã xác nhận. Vui lòng hủy và đặt lại.',
            'check_in.required' => 'Check-in date is required.',
            'check_in.after' => 'Check-in date must be after today.',
            'check_out.required' => 'Check-out date is required.',
            'check_out.after' => 'Check-out date must be after check-in date.',
            'special_requests.max' => 'Special requests cannot exceed 2000 characters.',
        ];
    }

    /**
     * Get the validated data from the request.
     *
     * Purify HTML fields (guest_name) to prevent XSS. Mirrors the sanitization
     * applied in StoreBookingRequest — update path must not be weaker than create.
     *
     * Only guest_name is purified. IDs, dates, statuses, and numeric fields are
     * domain-sensitive and must not be transformed here.
     *
     * Signature must match the parent `FormRequest::validated($key = null, $default = null)`.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        // If a specific key was requested, return it directly (parent handles default)
        if ($key !== null) {
            return $data;
        }

        if (is_array($data) && array_key_exists('guest_name', $data) && $data['guest_name'] !== null) {
            $data['guest_name'] = \App\Services\HtmlPurifierService::purify($data['guest_name']);
        }

        return $data;
    }
}
