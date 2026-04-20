<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Re-auditoria Camada 1 r3 — sec-04, sec-05.
 *
 * config/session.php deve defaultar para secure=true e same_site='strict'
 * quando APP_ENV=production. Em dev/local/testing, mantém comportamento
 * permissivo (secure=null, same_site='lax'). Env vars explícitas sempre
 * prevalecem (operador pode desabilitar secure em ambiente com TLS
 * terminado em LB, ou relaxar same_site para OAuth flow, etc).
 *
 * Mitigação: CSRF cross-site + session cookie servido em HTTP plain.
 */
class SessionCookieDefaultsTest extends TestCase
{
    public function test_production_defaults_secure_true_and_samesite_strict(): void
    {
        putenv('APP_ENV=production');
        putenv('SESSION_SECURE_COOKIE');
        putenv('SESSION_SAME_SITE');
        $_ENV['APP_ENV'] = 'production';
        unset($_ENV['SESSION_SECURE_COOKIE'], $_ENV['SESSION_SAME_SITE']);

        $config = require base_path('config/session.php');

        $this->assertTrue($config['secure'], 'session.secure deve ser true em produção por default');
        $this->assertSame('strict', $config['same_site'], "session.same_site deve ser 'strict' em produção por default");
    }

    public function test_non_production_defaults_secure_null_and_samesite_lax(): void
    {
        putenv('APP_ENV=local');
        putenv('SESSION_SECURE_COOKIE');
        putenv('SESSION_SAME_SITE');
        $_ENV['APP_ENV'] = 'local';
        unset($_ENV['SESSION_SECURE_COOKIE'], $_ENV['SESSION_SAME_SITE']);

        $config = require base_path('config/session.php');

        $this->assertFalse($config['secure'], 'session.secure deve ser false/null em dev');
        $this->assertSame('lax', $config['same_site']);
    }

    public function test_env_override_secure_false_in_production(): void
    {
        putenv('APP_ENV=production');
        putenv('SESSION_SECURE_COOKIE=false');
        $_ENV['APP_ENV'] = 'production';
        $_ENV['SESSION_SECURE_COOKIE'] = 'false';

        $config = require base_path('config/session.php');

        $this->assertFalse($config['secure'], 'env explícito false deve sobrescrever default prod');
    }

    public function test_env_override_samesite_lax_in_production(): void
    {
        putenv('APP_ENV=production');
        putenv('SESSION_SAME_SITE=lax');
        $_ENV['APP_ENV'] = 'production';
        $_ENV['SESSION_SAME_SITE'] = 'lax';

        $config = require base_path('config/session.php');

        $this->assertSame('lax', $config['same_site'], 'env explícito lax deve sobrescrever default prod');
    }
}
