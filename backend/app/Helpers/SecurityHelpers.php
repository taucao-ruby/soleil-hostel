<?php

/**
 * Global Helper Functions for Security Headers
 */
if (! function_exists('csp_nonce')) {
    /**
     * Get the current request's CSP nonce
     *
     * Usage in Blade:
     *   <script nonce="{{ csp_nonce() }}">...</script>
     *
     * Or use @nonce directive:
     *   <script nonce="@nonce">...</script>
     */
    function csp_nonce(): string
    {
        return request()->attributes->get('csp_nonce', '');
    }
}
