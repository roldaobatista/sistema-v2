<?php

$defaultOrigins = env('APP_ENV') === 'production'
    ? ''
    : 'http://localhost:5173,http://localhost:3000,http://127.0.0.1:5173';
$allowedOrigins = array_values(array_unique(array_filter(array_map(
    'trim',
    array_merge(
        explode(',', env('CORS_ALLOWED_ORIGINS', $defaultOrigins)),
        explode(',', env('FRONTEND_URL', ''))
    )
))));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'X-Request-ID', 'X-Webhook-Secret', 'X-Fiscal-Webhook-Secret'],
    'exposed_headers' => ['X-Request-ID'],
    'max_age' => 86400,
    'supports_credentials' => true,
];
