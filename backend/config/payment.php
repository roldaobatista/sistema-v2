<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Service Provider (PSP)
    |--------------------------------------------------------------------------
    |
    | Provedor de pagamento utilizado para gerar boletos e cobranças PIX.
    | Provedores suportados: "asaas"
    |
    */

    'provider' => env('PAYMENT_PROVIDER', 'asaas'),

    /*
    |--------------------------------------------------------------------------
    | Asaas
    |--------------------------------------------------------------------------
    |
    | Configurações do gateway Asaas (https://www.asaas.com).
    | Em sandbox, use https://sandbox.asaas.com/api/v3.
    | Em produção, use https://api.asaas.com/v3.
    |
    */

    'asaas' => [
        'api_url' => env('ASAAS_API_URL', 'https://sandbox.asaas.com/api/v3'),
        'api_key' => env('ASAAS_API_KEY'),
        'webhook_secret' => env('ASAAS_WEBHOOK_SECRET'),
        'circuit_breaker' => [
            'threshold' => (int) env('ASAAS_CB_THRESHOLD', 5),
            'timeout' => (int) env('ASAAS_CB_TIMEOUT', 120),
        ],
    ],

];
