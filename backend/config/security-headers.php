<?php

/**
 * Security Headers Configuration
 * 
 * Cấu hình chi tiết cho từng security header
 * Có thể override per environment bằng .env
 */

return [
    /**
     * CSP (Content-Security-Policy) Configuration
     */
    'csp' => [
        // Enable CSP reporting to detect violations
        'reporting_enabled' => env('CSP_REPORTING', true),
        
        // Report endpoint (relative URL)
        'report_uri' => '/api/csp-violation-report',
        
        // Nonce length (characters)
        'nonce_length' => 32,
    ],

    /**
     * HSTS (Strict-Transport-Security) Configuration
     */
    'hsts' => [
        // Max age in seconds (63072000 = 2 years)
        'max_age' => env('HSTS_MAX_AGE', 63072000),
        
        // Include subdomains
        'include_subdomains' => true,
        
        // Preload in HSTS preload list (https://hstspreload.org)
        'preload' => env('HSTS_PRELOAD', false),
    ],

    /**
     * Vite Configuration (for CSP nonce injection)
     */
    'vite' => [
        // Development server host (for CSP allow-list)
        'dev_host' => env('VITE_DEV_SERVER_HOST', 'localhost:5173'),
    ],

    /**
     * Whether to apply security headers in different environments
     */
    'enabled' => [
        'production' => true,
        'staging' => true,
        'local' => env('SECURITY_HEADERS_LOCAL', true),
        'testing' => false, // Disable in tests to simplify assertions
    ],
];
