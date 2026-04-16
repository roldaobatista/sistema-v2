<?php

use App\Support\Sentry\SentryFilters;
use Illuminate\Validation\ValidationException;

/**
 * Sentry Laravel SDK — Error tracking e performance.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */
return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
    'release' => env('SENTRY_RELEASE', '1.0.0'),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null ? 1.0 : (float) env('SENTRY_SAMPLE_RATE'),
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.2),
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),
    'enable_logs' => env('SENTRY_ENABLE_LOGS', false),
    'logs_channel_level' => env('SENTRY_LOG_LEVEL', env('LOG_LEVEL', 'warning')),
    'send_default_pii' => false,

    'ignore_transactions' => ['/up', '/api/health'],
    'ignore_exceptions' => [
        ValidationException::class,
    ],

    'before_send' => [SentryFilters::class, 'beforeSend'],

    'before_send_transaction' => [SentryFilters::class, 'beforeSendTransaction'],

    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => true,
        'sql_bindings' => true,
        'queue_info' => true,
        'command_info' => true,
        'cache' => true,
        'livewire' => env('SENTRY_BREADCRUMBS_LIVEWIRE_ENABLED', true),
        'http_client_requests' => true,
        'notifications' => true,
    ],
    'tracing' => [
        'queue_job_transactions' => true,
        'queue_jobs' => true,
        'sql_queries' => true,
        'sql_bindings' => false,
        'sql_origin' => true,
        'sql_origin_threshold_ms' => 100,
        'views' => true,
        'livewire' => true,
        'http_client_requests' => true,
        'cache' => true,
        'redis_commands' => false,
        'redis_origin' => true,
        'notifications' => true,
        'missing_routes' => false,
        'continue_after_response' => true,
        'default_integrations' => true,
    ],
];
