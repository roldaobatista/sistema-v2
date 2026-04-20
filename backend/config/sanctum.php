<?php

use App\Support\Config\SanctumStatefulResolver;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    */
    // Re-auditoria Camada 1 r3 — sec-03:
    // Em produção, fallback default é vazio (força configuração explícita via
    // SANCTUM_STATEFUL_DOMAINS). Em dev/local/testing, inclui localhost por
    // conveniência. Alinhado com config/cors.php (sec-02 r2).
    // Lógica extraída em SanctumStatefulResolver para permitir teste isolado.
    'stateful' => SanctumStatefulResolver::resolve(
        env('APP_ENV'),
        env('SANCTUM_STATEFUL_DOMAINS'),
        env('APP_URL'),
    ),

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
