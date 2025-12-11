<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'guest_email' => 'required|email|max:255'
        ];
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
