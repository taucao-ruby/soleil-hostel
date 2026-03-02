<?php

namespace App\Http\Requests;

use App\Services\HtmlPurifierService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and purifies contact form submissions.
 *
 * Extracted from ContactController::store() (M-05).
 */
class StoreContactRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => (string) __('messages.contact_name_required'),
            'name.max' => (string) __('messages.contact_name_max'),
            'email.required' => (string) __('messages.contact_email_required'),
            'email.email' => (string) __('messages.contact_email_email'),
            'subject.max' => (string) __('messages.contact_subject_max'),
            'message.required' => (string) __('messages.contact_message_required'),
            'message.max' => (string) __('messages.contact_message_max'),
        ];
    }

    /**
     * Get validated data with HTML purification applied.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if ($key !== null) {
            return $data;
        }

        if (is_array($data)) {
            $data['name'] = HtmlPurifierService::purify($data['name']);
            $data['subject'] = HtmlPurifierService::purify($data['subject'] ?? '');
            $data['message'] = HtmlPurifierService::purify($data['message']);
        }

        return $data;
    }
}
