<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateReviewRequest - Example FormRequest with validation + purification
 * 
 * Best practice: Purify in validated() method to ensure clean data
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
     * ✅ BEST PRACTICE: Purify here, not in controller
     * This ensures all user input is sanitized before controller processes it
     */
    public function validated(): array
    {
        // Get validated data first
        $validated = parent::validated();

        // Purify HTML content fields
        // This strips dangerous tags/attributes while preserving safe formatting
        return $this->purify(['title', 'content']);
    }
}
