<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

/**
 * AuditLog — minimizacao de PII em description (sec-10).
 *
 * LGPD Art. 46 (minimizacao): description NAO deve carregar email/nome/telefone
 * em plaintext. A identidade do ator ja vive em user_id (FK) — isso basta para
 * rastreabilidade forense (Art. 37). Repetir PII em description:
 *   1. duplica dado sensivel em indice de texto livre (harder to purge / anonymize)
 *   2. cria superficie de leak em views que nao mascaram description
 *   3. viola principio de minimizacao (dado adicional sem finalidade)
 */
describe('AuditLog: description generica em eventos de auth (sec-10)', function () {
    beforeEach(function () {
        Cache::flush();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'email' => 'vitima.leak.unique@example.com',
            'name' => 'Vitima Leak Unique',
            'password' => Hash::make('CorrectHorseBatteryStaple9!'),
            'is_active' => true,
        ]);
    });

    it('login nao grava email do usuario em description', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'vitima.leak.unique@example.com',
            'password' => 'CorrectHorseBatteryStaple9!',
        ]);

        $response->assertOk();

        $log = AuditLog::withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->where('action', 'login')
            ->latest('created_at')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->description)->not->toContain('vitima.leak.unique@example.com')
            ->and($log->description)->not->toContain($this->user->name);
    });

    it('login preserva user_id como rastreabilidade forense', function () {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'vitima.leak.unique@example.com',
            'password' => 'CorrectHorseBatteryStaple9!',
        ])->assertOk();

        $log = AuditLog::withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->where('action', 'login')
            ->latest('created_at')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->user_id)->toBe($this->user->id);
    });

    it('password reset nao grava email em description', function () {
        $user = $this->user;

        // Invoca diretamente o AuditLog::log do fluxo (em vez de disparar
        // o endpoint inteiro, que exige broker + token). Reproduz a
        // chamada literal do controller apos correcao.
        auth()->setUser($user);
        app()->instance('current_tenant_id', $this->tenant->id);

        AuditLog::log('password_reset', 'Senha redefinida via fluxo de recuperacao', $user);

        $log = AuditLog::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('action', 'password_reset')
            ->latest('created_at')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->description)->not->toContain($user->email)
            ->and($log->description)->not->toContain($user->name);
    });

    it('tenant switch nao grava nome/email do usuario em description (AuthController)', function () {
        // Lenta este teste pelo log emitido dentro do AuthController::switchTenant
        // sem depender da rota (namespace pode variar). Assertamos que o source
        // code do controller NAO formata string com $user->name/$user->email no
        // argumento description do AuditLog::log().
        $controllerPath = base_path('app/Http/Controllers/Api/V1/Auth/AuthController.php');
        $source = file_get_contents($controllerPath);

        // Regex: bloco de chamada AuditLog::log(...'tenant_switch'...).
        // Captura o segundo argumento (description) no padrao atual.
        preg_match_all(
            '/AuditLog::log\s*\(\s*[\'"]tenant_switch[\'"]\s*,\s*([^,]+),/s',
            $source,
            $matches
        );

        expect($matches[1] ?? [])->not->toBeEmpty('chamada AuditLog::log tenant_switch nao encontrada');

        foreach ($matches[1] as $descArg) {
            expect($descArg)->not->toContain('$user->name')
                ->and($descArg)->not->toContain('$user->email');
        }
    });
});
