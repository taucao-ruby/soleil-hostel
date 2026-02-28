<?php

namespace App\Macros;

use App\Services\HtmlPurifierService;

/**
 * FormRequest Macro - Purify validated data
 *
 * Usage:
 * $request->validate([
 *     'name' => 'required|string',
 *     'message' => 'required|string',
 * ]);
 *
 * $request->purify(['message']);  // Purify 'message' field only
 * $request->purifyAll();           // Purify all validated data
 *
 * Or in a FormRequest class:
 * public function prepareForValidation()
 * {
 *     $this->merge([
 *         'message' => HtmlPurifierService::purify($this->message),
 *     ]);
 * }
 */
class FormRequestPurifyMacro
{
    /**
     * Register macro into FormRequest
     *
     * Called in AppServiceProvider::boot()
     */
    public static function register(): void
    {
        // Macro: purify specific fields
        \Illuminate\Foundation\Http\FormRequest::macro(
            'purify',
            function (array $fields = []) {
                $validated = $this->validated();

                foreach ($fields as $field) {
                    if (isset($validated[$field]) && is_string($validated[$field])) {
                        $validated[$field] = HtmlPurifierService::purify($validated[$field]);
                    }
                }

                return $validated;
            }
        );

        // Macro: purify all string fields
        \Illuminate\Foundation\Http\FormRequest::macro(
            'purifyAll',
            function () {
                $validated = $this->validated();

                foreach ($validated as $key => &$value) {
                    if (is_string($value)) {
                        $value = HtmlPurifierService::purify($value);
                    }
                }

                return $validated;
            }
        );
    }
}
