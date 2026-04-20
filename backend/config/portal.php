<?php

/*
|--------------------------------------------------------------------------
| Portal do Cliente — configuração de segurança/autenticação
|--------------------------------------------------------------------------
|
| Valores aqui ativam controles do `PortalAuthController`.
| Decisões arquiteturais em `docs/TECHNICAL-DECISIONS.md` §14.30–32.
*/

return [

    /*
    | sec-portal-login-no-email-verification — Camada 1 r4 Batch C (S3)
    |
    | Exige `client_portal_users.email_verified_at` preenchido para permitir
    | login. Espelha `config('auth.require_email_verified')` do painel web.
    | Override temporário via env PORTAL_REQUIRE_EMAIL_VERIFIED=false.
    */
    'require_email_verified' => (bool) env('PORTAL_REQUIRE_EMAIL_VERIFIED', true),

];
