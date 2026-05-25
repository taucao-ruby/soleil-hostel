<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
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
        return [
            'room_id' => 'required|integer|exists:rooms,id',
            'check_in' => 'required|date_format:Y-m-d|after_or_equal:today',
            'check_out' => 'required|date_format:Y-m-d|after:check_in',
            'guest_name' => 'required|string|min:2|max:255',
            'guest_email' => 'required|email|max:255',
            'number_of_guests' => 'nullable|integer|min:1',
            'special_requests' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Cross-field validation: number_of_guests must not exceed the room's max_guests.
     *
     * Fires only after base rules pass so room_id is guaranteed valid at this point.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $numberOfGuests = $this->integer('number_of_guests', 0);
            $roomId = $this->integer('room_id', 0);

            if ($numberOfGuests <= 0 || $roomId <= 0) {
                return;
            }

            $room = Room::find($roomId);

            if ($room && $numberOfGuests > $room->max_guests) {
                $validator->errors()->add(
                    'number_of_guests',
                    "Number of guests cannot exceed the room's maximum capacity of {$room->max_guests}."
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'room_id.required' => 'Room is required.',
            'room_id.exists' => 'Selected room does not exist.',
            'check_in.required' => 'Check-in date is required.',
            'check_in.after' => 'Check-in date must be after today.',
            'check_out.required' => 'Check-out date is required.',
            'check_out.after' => 'Check-out date must be after check-in date.',
            'guest_name.required' => 'Guest name is required.',
            'guest_name.min' => 'Guest name must be at least 2 characters.',
            'guest_name.max' => 'Guest name cannot exceed 255 characters.',
            'guest_email.required' => 'Guest email is required.',
            'guest_email.email' => 'Guest email must be a valid email address.',
            'number_of_guests.integer' => 'Number of guests must be a whole number.',
            'number_of_guests.min' => 'Number of guests must be at least 1.',
            'special_requests.max' => 'Special requests cannot exceed 2000 characters.',
        ];
    }

    /**
     * Get the validated data from the request.
     *
     * Purify HTML fields (guest_name) to prevent XSS. Uses HTML Purifier whitelist.
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
