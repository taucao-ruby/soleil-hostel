<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Harness Feature Flag
    |--------------------------------------------------------------------------
    |
    | Master kill switch. When false, all AI endpoints return 404.
    | Default: false in ALL environments. Explicitly enable per environment.
    |
    */

    'enabled' => env('AI_HARNESS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Default Model Provider
    |--------------------------------------------------------------------------
    */

    'default_provider' => env('AI_DEFAULT_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('AI_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'max_tokens' => 1024,
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('AI_OPENAI_MODEL', 'gpt-4o'),
            'max_tokens' => 1024,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout Ladder (seconds per task type)
    |--------------------------------------------------------------------------
    */

    'timeout_ladder' => [
        'faq_lookup' => 3,
        'room_discovery' => 8,
        'booking_status' => 5,
        'admin_draft' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker (per provider)
    |--------------------------------------------------------------------------
    */

    'circuit_breaker' => [
        'failure_threshold' => 5,
        'recovery_timeout' => 30, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Policy
    |--------------------------------------------------------------------------
    */

    'retry' => [
        'max_attempts' => 2,
        'backoff_ms' => [500, 2000],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Budget (max input tokens per task type)
    |--------------------------------------------------------------------------
    */

    'token_budget' => [
        'faq_lookup' => 2000,
        'room_discovery' => 4000,
        'booking_status' => 2000,
        'admin_draft' => 6000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Alerting
    |--------------------------------------------------------------------------
    */

    'cost_alert_threshold_usd' => 0.05,

    /*
    |--------------------------------------------------------------------------
    | AI-Specific Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limit' => [
        'per_user_per_minute' => 10,
        'global_per_minute' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Canary Routing (percentage of traffic per task type)
    |--------------------------------------------------------------------------
    */

    'canary' => [
        'faq_lookup_percentage' => (int) env('AI_CANARY_FAQ_LOOKUP_PCT', 5),
        'room_discovery_percentage' => (int) env('AI_CANARY_ROOM_DISCOVERY_PCT', 5),
        'admin_draft_percentage' => (int) env('AI_CANARY_ADMIN_DRAFT_PCT', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Regression Gate (nightly eval)
    |--------------------------------------------------------------------------
    */

    'regression_gate' => [
        'enabled' => env('AI_REGRESSION_GATE_ENABLED', true),
        'max_hallucination_rate' => 2.0,
        'max_third_party_pii' => 0,
        'max_autonomous_action' => 0,
        'notify_channel' => 'ai',
    ],

];
