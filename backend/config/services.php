<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'evolution' => [
        'url' => env('EVOLUTION_API_URL'),
        'api_key' => env('EVOLUTION_API_KEY'),
        'instance' => env('EVOLUTION_INSTANCE', 'default'),
    ],

    'fiscal' => [
        'provider' => env('FISCAL_PROVIDER', 'focusnfe'),
    ],

    'fiscal_external' => [
        'base_url' => env('FISCAL_EXTERNAL_BASE_URL', 'https://api.exemplo.com.br'),
        'token' => env('FISCAL_EXTERNAL_TOKEN', ''),
        'webhook_secret' => env('FISCAL_WEBHOOK_SECRET', ''),
    ],

    'focusnfe' => [
        'token' => env('FOCUSNFE_TOKEN'),
        'environment' => env('FOCUSNFE_ENV', 'homologation'),
        'url_production' => 'https://api.focusnfe.com.br',
        'url_homologation' => 'https://homologacao.focusnfe.com.br',
    ],

    'nuvemfiscal' => [
        'url' => env('NUVEMFISCAL_URL', 'https://api.nuvemfiscal.com.br'),
        'client_id' => env('NUVEMFISCAL_CLIENT_ID'),
        'client_secret' => env('NUVEMFISCAL_CLIENT_SECRET'),
    ],

    'auvo' => [
        'api_key' => env('AUVO_API_KEY'),
        'api_token' => env('AUVO_API_TOKEN'),
        'ssl_cafile' => env('AUVO_SSL_CAFILE'),
        'ssl_verify' => env('AUVO_SSL_VERIFY', true),
    ],

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', env('APP_URL', 'http://localhost')),
    ],

    /*
    | Collection / Régua de cobrança — SMS
    | Driver: log (apenas log) | twilio (requer TWILIO_* no .env)
    */
    'collection_sms' => [
        'driver' => env('COLLECTION_SMS_DRIVER', 'log'),
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_PHONE_NUMBER'),
        ],
    ],

    'webhook_secret' => env('WEBHOOK_SECRET'),

    'observability' => [
        'otel_endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', ''),
        'jaeger_url' => env('OBSERVABILITY_JAEGER_URL', 'http://localhost:16686'),
    ],

];
