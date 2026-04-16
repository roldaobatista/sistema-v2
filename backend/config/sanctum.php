<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    */
    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Tokens expiram após este número de minutos de inatividade.
    | null = sem expiração. 10080 = 7 dias.
    |
    */
    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 10080),

    /*
    |--------------------------------------------------------------------------
    | Token em cookie httpOnly (opcional)
    |--------------------------------------------------------------------------
    |
    | Quando true, o login define o token em um cookie httpOnly (mais seguro que localStorage).
    | O frontend deve usar withCredentials e não armazenar o token em localStorage.
    |
    */
    'use_token_cookie' => env('SANCTUM_USE_TOKEN_COOKIE', false),
    'cookie_name' => env('SANCTUM_AUTH_COOKIE_NAME', 'auth_token'),

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    */
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
