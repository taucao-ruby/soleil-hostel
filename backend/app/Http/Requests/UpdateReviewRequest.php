<?php

namespace App\Http\Requests;

use App\Services\HtmlPurifierService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateReviewRequest - FormRequest with validation + purification
 *
 * Purifies user input via HtmlPurifierService in validated() method
 */
class UpdateReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && (
            auth()->user()->isAdmin() ||
            auth()->id() === $this->review->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => [
                'nullable',
                'string',
                'max:200',
            ],
            'content' => [
                'nullable',
                'string',
                'max:5000',
                'min:10',
            ],
            'rating' => [
                'nullable',
                'integer',
                'min:1',
                'max:5',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'content.min' => 'Review must be at least 10 characters.',
            'content.max' => 'Review cannot exceed 5000 characters.',
            'rating.min' => 'Rating must be between 1 and 5.',
            'rating.max' => 'Rating must be between 1 and 5.',
        ];
    }

    /**
     * Get the validated data from the request.
     *
     * Purifies HTML content fields before controller processes the data.
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if ($key !== null || ! is_array($validated)) {
            return $validated;
        }

        foreach (['title', 'content'] as $field) {
            if (isset($validated[$field]) && is_string($validated[$field])) {
                $validated[$field] = HtmlPurifierService::purify($validated[$field]);
            }
        }

        return $validated;
    }
}
