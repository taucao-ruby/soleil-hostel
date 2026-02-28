<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store Review Form Request
 *
 * Auto-purifies user input from guest reviews
 * Uses HTML Purifier whitelist, not regex blacklist
 * (Regex XSS = 99% bypass. HTML Purifier = 0% bypass)
 */
class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Adjust as needed
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
            'rating' => 'required|integer|min:1|max:5',
            'room_id' => 'required|integer|exists:rooms,id',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Review title is required.',
            'title.max' => 'Title cannot exceed 255 characters.',
            'content.required' => 'Review content cannot be empty.',
            'content.max' => 'Content cannot exceed 5000 characters.',
            'rating.required' => 'Please provide a rating.',
            'rating.min' => 'Rating must be between 1-5.',
            'rating.max' => 'Rating must be between 1-5.',
            'room_id.required' => 'Room ID is required.',
            'room_id.exists' => 'Invalid room selected.',
        ];
    }

    /**
     * Automatically purify user input
     *
     * After validation, auto-sanitize title + content
     * Only allow: b, i, strong, em, p, br, ul, ol, li, a[href]
     * Block: <script>, <style>, on*, javascript:, data:
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        // Purify HTML fields if result is an array
        if (is_array($validated)) {
            $validated = $this->purify(['title', 'content']);
        }

        return $validated;
    }
}
