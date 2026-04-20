<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

/**
 * AuditLog — imutabilidade e integridade forense.
 *
 * Findings cobertos (r3 re-auditoria Camada 1, 2026-04-19):
 *  - sec-09:  resolveTenantId NUNCA grava NULL (fallback tenant_id=0 "system")
 *  - sec-14:  user_agent truncado em 255 chars + caracteres de controle sanitizados
 *  - sec-16:  $fillable nao permite forjar tenant_id / user_id / created_at / ip_address / user_agent
 *  - sec-25:  user_id = 0 ("system") quando auth()->id() e null (jobs/console)
 */
describe('AuditLog: tenant_id sempre preenchido (sec-09)', function () {
    it('fallback tenant_id=0 quando nao ha model, app binding, ou usuario autenticado', function () {
        // Nenhuma fonte de tenant: sem model, sem current_tenant_id, sem user autenticado.
        app()->forgetInstance('current_tenant_id');
        auth()->logout();

        $log = AuditLog::log('login', 'Acao sem contexto de tenant');

        expect($log->tenant_id)->not->toBeNull()
            ->and($log->tenant_id)->toBe(0);
    });

    it('fallback tenant_id=0 quando user autenticado nao tem tenant', function () {
        app()->forgetInstance('current_tenant_id');

        $user = new User;
        $user->id = 999999;
        $user->current_tenant_id = null;
        $user->tenant_id = null;

        auth()->setUser($user);

        $log = AuditLog::log('login', 'Login sem tenant');

        expect($log->tenant_id)->toBe(0);
    });

    it('registros com tenant_id=0 sao consultaveis via query especifica', function () {
        app()->forgetInstance('current_tenant_id');
        auth()->logout();

        AuditLog::log('login', 'System action A');
        AuditLog::log('logout', 'System action B');

        // withoutGlobalScope pois BelongsToTenant filtra por tenant atual.
        $count = AuditLog::withoutGlobalScopes()->where('tenant_id', 0)->count();

        expect($count)->toBe(2);
    });
});

describe('AuditLog: user_id fallback para sistema (sec-25)', function () {
    it('user_id=0 quando auth()->id() retorna null (job/console)', function () {
        auth()->logout();
        app()->forgetInstance('current_tenant_id');

        $log = AuditLog::log('created', 'Acao automatica de job');

        expect($log->user_id)->not->toBeNull()
            ->and($log->user_id)->toBe(0);
    });

    it('user_id preenchido normalmente quando ha usuario autenticado', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        auth()->setUser($user);
        app()->instance('current_tenant_id', $tenant->id);

        $log = AuditLog::log('login', 'Login normal');

        expect($log->user_id)->toBe($user->id);
    });
});

describe('AuditLog: user_agent truncado e sanitizado (sec-14)', function () {
    it('trunca user_agent longo para 255 chars antes de gravar', function () {
        $longUa = str_repeat('A', 5000);
        request()->headers->set('User-Agent', $longUa);

        app()->forgetInstance('current_tenant_id');
        auth()->logout();

        $log = AuditLog::log('login', 'Login com UA gigante');

        expect(strlen((string) $log->user_agent))->toBeLessThanOrEqual(255);
    });

    it('sanitiza caracteres de controle no user_agent (log injection)', function () {
        $malicious = "Mozilla/5.0\r\nX-Injected: evil\nFake: line\0nullbyte";
        request()->headers->set('User-Agent', $malicious);

        app()->forgetInstance('current_tenant_id');
        auth()->logout();

        $log = AuditLog::log('login', 'Login com UA malicioso');

        expect($log->user_agent)->not->toContain("\r")
            ->and($log->user_agent)->not->toContain("\n")
            ->and($log->user_agent)->not->toContain("\0");
    });

    it('aceita user_agent normal sem alteracao indevida', function () {
        $normal = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        request()->headers->set('User-Agent', $normal);

        app()->forgetInstance('current_tenant_id');
        auth()->logout();

        $log = AuditLog::log('login', 'Login normal');

        expect($log->user_agent)->toBe($normal);
    });
});

describe('AuditLog: $fillable nao permite forjar evidencia (sec-16)', function () {
    it('$fillable NAO contem tenant_id', function () {
        expect((new AuditLog)->getFillable())->not->toContain('tenant_id');
    });

    it('$fillable NAO contem user_id', function () {
        expect((new AuditLog)->getFillable())->not->toContain('user_id');
    });

    it('$fillable NAO contem created_at (anti-backdate)', function () {
        expect((new AuditLog)->getFillable())->not->toContain('created_at');
    });

    it('$fillable NAO contem ip_address', function () {
        expect((new AuditLog)->getFillable())->not->toContain('ip_address');
    });

    it('$fillable NAO contem user_agent', function () {
        expect((new AuditLog)->getFillable())->not->toContain('user_agent');
    });

    it('mass-assignment via create() rejeita campos protegidos com MassAssignmentException (strict mode)', function () {
        $tenant = Tenant::factory()->create();

        // Model::shouldBeStrict(true) em dev/test (AppServiceProvider) transforma
        // tentativa de atribuir atributo fora de $fillable em exception explicita.
        // Em producao (strict=false) o comportamento e silent discard — ambos fecham
        // o vetor de forjar evidencia, mas strict em dev acha o bug mais cedo.
        expect(fn () => AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => 99999,
            'action' => AuditAction::LOGIN,
            'description' => 'Tentativa de forjar',
            'ip_address' => '1.2.3.4',
            'user_agent' => 'Evil UA',
            'created_at' => '1970-01-01 00:00:00',
        ]))->toThrow(MassAssignmentException::class);
    });
});
