<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'room_id' => 'sometimes|integer|exists:rooms,id',
            'check_in' => 'required|date_format:Y-m-d|after_or_equal:today',
            'check_out' => 'required|date_format:Y-m-d|after:check_in',
            'guest_name' => 'required|string|min:2|max:255',
            'guest_email' => 'required|email|max:255',
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
            'check_in.required' => 'Check-in date is required.',
            'check_in.after' => 'Check-in date must be after today.',
            'check_out.required' => 'Check-out date is required.',
            'check_out.after' => 'Check-out date must be after check-in date.',
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
