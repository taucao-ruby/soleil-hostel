<?php

use App\Logging\SensitiveDataProcessor;

/**
 * Sentry Laravel SDK configuration.
 *
 * Keep PII collection disabled regardless of environment variables. The
 * before_send hook is a final outbound scrub before an event leaves the app.
 */
return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    'release' => env('SENTRY_RELEASE'),

    'environment' => env('SENTRY_ENVIRONMENT'),

    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null ? 1.0 : (float) env('SENTRY_SAMPLE_RATE'),

    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_TRACES_SAMPLE_RATE'),

    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_PROFILES_SAMPLE_RATE'),

    'enable_logs' => env('SENTRY_ENABLE_LOGS', false),

    'logs_channel_level' => env('SENTRY_LOG_LEVEL', env('SENTRY_LOGS_LEVEL', env('LOG_LEVEL', 'debug'))),

    'send_default_pii' => false,

    'max_request_body_size' => 'never',

    'before_send' => [SensitiveDataProcessor::class, 'scrubSentryEvent'],

    'ignore_transactions' => [
        '/up',
        '/api/health/live',
    ],

    'breadcrumbs' => [
        'logs' => env('SENTRY_BREADCRUMBS_LOGS_ENABLED', true),
        'cache' => env('SENTRY_BREADCRUMBS_CACHE_ENABLED', true),
        'livewire' => env('SENTRY_BREADCRUMBS_LIVEWIRE_ENABLED', true),
        'sql_queries' => env('SENTRY_BREADCRUMBS_SQL_QUERIES_ENABLED', true),
        'sql_bindings' => env('SENTRY_BREADCRUMBS_SQL_BINDINGS_ENABLED', false),
        'queue_info' => env('SENTRY_BREADCRUMBS_QUEUE_INFO_ENABLED', true),
        'command_info' => env('SENTRY_BREADCRUMBS_COMMAND_JOBS_ENABLED', true),
        'http_client_requests' => env('SENTRY_BREADCRUMBS_HTTP_CLIENT_REQUESTS_ENABLED', true),
        'notifications' => env('SENTRY_BREADCRUMBS_NOTIFICATIONS_ENABLED', true),
    ],

    'tracing' => [
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', true),
        'queue_jobs' => env('SENTRY_TRACE_QUEUE_JOBS_ENABLED', true),
        'sql_queries' => env('SENTRY_TRACE_SQL_QUERIES_ENABLED', true),
        'sql_bindings' => env('SENTRY_TRACE_SQL_BINDINGS_ENABLED', false),
        'sql_origin' => env('SENTRY_TRACE_SQL_ORIGIN_ENABLED', true),
        'sql_origin_threshold_ms' => env('SENTRY_TRACE_SQL_ORIGIN_THRESHOLD_MS', 100),
        'views' => env('SENTRY_TRACE_VIEWS_ENABLED', true),
        'livewire' => env('SENTRY_TRACE_LIVEWIRE_ENABLED', true),
        'http_client_requests' => env('SENTRY_TRACE_HTTP_CLIENT_REQUESTS_ENABLED', true),
        'cache' => env('SENTRY_TRACE_CACHE_ENABLED', true),
        'redis_commands' => env('SENTRY_TRACE_REDIS_COMMANDS', false),
        'redis_origin' => env('SENTRY_TRACE_REDIS_ORIGIN_ENABLED', true),
        'notifications' => env('SENTRY_TRACE_NOTIFICATIONS_ENABLED', true),
        'missing_routes' => env('SENTRY_TRACE_MISSING_ROUTES_ENABLED', false),
        'continue_after_response' => env('SENTRY_TRACE_CONTINUE_AFTER_RESPONSE', true),
        'default_integrations' => env('SENTRY_TRACE_DEFAULT_INTEGRATIONS_ENABLED', true),
    ],
];
