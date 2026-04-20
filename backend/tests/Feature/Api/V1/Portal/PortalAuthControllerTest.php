<?php

namespace Tests\Feature\Api\V1\Portal;

use App\Models\ClientPortalUser;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalAuthControllerTest extends TestCase
{
    private Tenant $tenant;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function createPortalUser(string $email = 'portal@cliente.com', string $password = 'PortalPass!123', bool $active = true, bool $verified = true): ClientPortalUser
    {
        // forceCreate: $fillable de ClientPortalUser não aceita tenant_id/is_active
        // por segurança (sec-18 + data-02). Em strict mode (Model::shouldBeStrict)
        // um create() simples lança MassAssignmentException.
        // refresh(): força SELECT * para hidratar todas as colunas (locked_until,
        // failed_login_attempts, etc) — middleware EnsurePortalAccess acessa
        // esses campos e strict mode lança MissingAttributeException se não
        // forem hidratados.
        //
        // $verified: default true para não quebrar testes existentes após a
        // introdução de sec-portal-login-no-email-verification (§14.32 ref).
        // Testes específicos de verificação forçam null via forceFill.
        $user = ClientPortalUser::forceCreate([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Portal User',
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => $active,
            'email_verified_at' => $verified ? now() : null,
        ]);

        return $user->refresh();
    }

    private function createActiveContract(): Contract
    {
        return Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'active',
        ]);
    }

    public function test_login_validates_required_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/portal/login');

        // PortalLoginRequest requer email + password
        $response->assertStatus(422);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->createPortalUser('cliente1@test.com', 'CorrectPass!1');

        $response = $this->postJson('/api/v1/portal/login', [
            'email' => 'cliente1@test.com',
            'password' => 'WrongPassword',
        ]);

        // Credenciais erradas -> 422 via ApiResponse::message
        $response->assertStatus(422);
    }

    public function test_login_succeeds_with_valid_credentials_and_active_contract(): void
    {
        $this->createPortalUser('cliente2@test.com', 'CorrectPass!1');
        $this->createActiveContract();

        $response = $this->postJson('/api/v1/portal/login', [
            'email' => 'cliente2@test.com',
            'password' => 'CorrectPass!1',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'email'],
                ],
            ])
            ->assertJsonMissingPath('data.user.tenant_id')
            ->assertJsonMissingPath('data.user.customer_id')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.customer.tenant_id');
    }

    public function test_login_ignores_payload_tenant_id_and_uses_server_side_user_tenant(): void
    {
        $wrongTenant = Tenant::factory()->create();
        $this->createPortalUser('cliente3@test.com', 'CorrectPass!1');
        $this->createActiveContract();

        $response = $this->postJson('/api/v1/portal/login', [
            'tenant_id' => $wrongTenant->id,
            'email' => 'cliente3@test.com',
            'password' => 'CorrectPass!1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'cliente3@test.com')
            ->assertJsonMissingPath('data.user.tenant_id')
            ->assertJsonMissingPath('data.user.customer_id')
            ->assertJsonMissingPath('data.user.customer.tenant_id');
    }

    public function test_me_returns_sanitized_portal_user_payload(): void
    {
        $user = $this->createPortalUser('me@test.com', 'CorrectPass!1');
        Sanctum::actingAs($user, ['portal:access']);

        $response = $this->getJson('/api/v1/portal/me');

        $response->assertOk()
            ->assertJsonPath('data.email', 'me@test.com')
            ->assertJsonMissingPath('data.tenant_id')
            ->assertJsonMissingPath('data.customer_id')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.customer.tenant_id');
    }

    public function test_login_blocked_when_no_active_contract(): void
    {
        $this->createPortalUser('sem_contrato@test.com', 'CorrectPass!1');
        // Sem contrato ativo criado

        $response = $this->postJson('/api/v1/portal/login', [
            'email' => 'sem_contrato@test.com',
            'password' => 'CorrectPass!1',
        ]);

        $response->assertStatus(422); // ValidationException "Nenhum contrato ativo"
    }

    public function test_login_blocks_inactive_portal_user(): void
    {
        $this->createPortalUser('inativo@test.com', 'CorrectPass!1', active: false);
        $this->createActiveContract();

        $response = $this->postJson('/api/v1/portal/login', [
            'email' => 'inativo@test.com',
            'password' => 'CorrectPass!1',
        ]);

        $response->assertStatus(422); // "Sua conta esta inativa"
    }

    // ---------------------------------------------------------------------
    // sec-portal-lockout-not-enforced-on-login — Camada 1 r4 Batch B
    // Lockout persistente em DB (locked_until / failed_login_attempts) DEVE
    // ser enforçado no login do portal. Antes de r4 só havia throttle por
    // IP+email em cache — bypassável via rotação de IP.
    // ---------------------------------------------------------------------

    public function test_login_rejected_when_client_portal_user_locked_until_is_in_future(): void
    {
        $user = $this->createPortalUser('locked@test.com', 'CorrectPass!1');
        $this->createActiveContract();

        $user->forceFill([
            'locked_until' => now()->addMinutes(30),
            'failed_login_attempts' => 5,
        ])->save();

        $response = $this->postJson('/api/v1/portal/login', [
            'email' => 'locked@test.com',
            'password' => 'CorrectPass!1', // senha correta — ainda assim negar
        ]);

        $response->assertStatus(423)
            ->assertJsonStructure(['message']);
    }

    public function test_login_allows_access_when_locked_until_already_expired(): void
    {
        $user = $this->createPortalUser('unlocked@test.com', 'CorrectPass!1');
        $this->createActiveContract();

        $user->forceFill([
            'locked_until' => now()->subMinutes(5), // expirado
            'failed_login_attempts' => 5,
        ])->save();

        $response = $this->postJson('/api/v1/portal/login', [
            'email' => 'unlocked@test.com',
            'password' => 'CorrectPass!1',
        ]);

        $response->assertOk();
    }

    public function test_login_increments_failed_attempts_on_wrong_password(): void
    {
        $user = $this->createPortalUser('fail1@test.com', 'CorrectPass!1');
        $this->createActiveContract();

        $this->postJson('/api/v1/portal/login', [
            'email' => 'fail1@test.com',
            'password' => 'WrongPass!',
        ]);

        $user->refresh();
        $this->assertSame(1, (int) $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }

    public function test_login_locks_account_after_five_consecutive_failures(): void
    {
        $user = $this->createPortalUser('fail5@test.com', 'CorrectPass!1');
        $this->createActiveContract();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/portal/login', [
                'email' => 'fail5@test.com',
                'password' => 'WrongPass!',
            ]);
        }

        $user->refresh();
        $this->assertSame(5, (int) $user->failed_login_attempts);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
    }

    public function test_login_resets_failed_attempts_and_locked_until_on_success(): void
    {
        $user = $this->createPortalUser('reset@test.com', 'CorrectPass!1');
        $this->createActiveContract();

        $user->forceFill([
            'failed_login_attempts' => 3,
            'locked_until' => null,
        ])->save();

        $response = $this->postJson('/api/v1/portal/login', [
            'email' => 'reset@test.com',
            'password' => 'CorrectPass!1',
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertSame(0, (int) $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }

    // ---------------------------------------------------------------------
    // Batch C — Camada 1 r4
    //
    // sec-portal-throttle-toctou (S3): throttle deve ser atômico (Cache::add/
    // increment). Testa que contador não pode ser sobrescrito.
    // sec-portal-tenant-enumeration-bypass (S3): respostas uniformes (§14.30).
    // sec-portal-audit-missing (S3): login/logout/falhas gravam audit (§14.31).
    // sec-portal-login-no-email-verification (S3): bloquear login se email
    // não verificado, com flag config('portal.require_email_verified').
    // ---------------------------------------------------------------------

    public function test_throttle_counter_is_atomic_not_overwritten_by_concurrent_requests(): void
    {
        // TOCTOU fix: migrar de Cache::get+put para Cache::add+increment.
        // Se duas requisições lerem o mesmo $attempts=3, ambas gravam 4 — contador
        // não avança. Com increment atômico, uma grava 4 e a próxima grava 5.
        $this->createPortalUser('toctou@test.com', 'CorrectPass!1');
        $this->createActiveContract();

        // Envia 3 requisições com senha errada sequencialmente.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/portal/login', [
                'email' => 'toctou@test.com',
                'password' => 'WrongPass!',
            ]);
        }

        // Contador em cache deve refletir exatamente 3 tentativas.
        $key = sprintf('portal_login_attempts:%s:%s', '127.0.0.1', 'toctou@test.com');
        $this->assertSame(3, (int) Cache::get($key));
    }

    public function test_tenant_enumeration_invalid_tenant_and_wrong_password_return_identical_response(): void
    {
        // sec-portal-tenant-enumeration-bypass: atacante não deve distinguir
        // tenant inválido/inexistente de senha errada via status+body.
        $this->createPortalUser('enum@test.com', 'CorrectPass!1');
        $this->createActiveContract();

        // (a) tenant válido + senha errada
        $wrongPass = $this->postJson('/api/v1/portal/login', [
            'email' => 'enum@test.com',
            'password' => 'WrongPass!',
        ]);

        // (b) email inexistente em tenant válido
        $noUser = $this->postJson('/api/v1/portal/login', [
            'email' => 'inexistente@test.com',
            'password' => 'AnyPass!1',
        ]);

        // Status + mensagem devem ser idênticos — sem distinguir o caso.
        $this->assertSame($wrongPass->status(), $noUser->status());
        $this->assertSame(
            $wrongPass->json('message'),
            $noUser->json('message'),
            'Respostas precisam ser uniformes para mitigar enumeração.'
        );
    }

    public function test_tenant_enumeration_inactive_user_and_no_contract_return_same_generic_message(): void
    {
        // Usuário inativo
        $this->createPortalUser('inat@test.com', 'CorrectPass!1', active: false);
        $this->createActiveContract();
        $inactive = $this->postJson('/api/v1/portal/login', [
            'email' => 'inat@test.com',
            'password' => 'CorrectPass!1',
        ]);

        // Sem contrato ativo — limpa contratos para isolar o cenário.
        Contract::query()->where('tenant_id', $this->tenant->id)->delete();
        $this->createPortalUser('nocont@test.com', 'CorrectPass!1');
        $noContract = $this->postJson('/api/v1/portal/login', [
            'email' => 'nocont@test.com',
            'password' => 'CorrectPass!1',
        ]);

        // Ambas devem cair em 422 com mesma mensagem genérica (sem vazar razão).
        $this->assertSame(422, $inactive->status());
        $this->assertSame(422, $noContract->status());
        $this->assertSame(
            $inactive->json('message'),
            $noContract->json('message'),
            'Respostas específicas (inativo vs sem contrato) vazam estado interno.'
        );
    }

    public function test_login_success_records_audit_log(): void
    {
        $this->createPortalUser('audit_ok@test.com', 'CorrectPass!1');
        $this->createActiveContract();

        $this->postJson('/api/v1/portal/login', [
            'email' => 'audit_ok@test.com',
            'password' => 'CorrectPass!1',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'portal_login_success',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_login_failure_records_audit_log_with_failed_action(): void
    {
        $this->createPortalUser('audit_fail@test.com', 'CorrectPass!1');
        $this->createActiveContract();

        $this->postJson('/api/v1/portal/login', [
            'email' => 'audit_fail@test.com',
            'password' => 'WrongPass!',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'portal_login_failed',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_login_locked_account_records_audit_log_with_locked_action(): void
    {
        $user = $this->createPortalUser('audit_locked@test.com', 'CorrectPass!1');
        $this->createActiveContract();

        $user->forceFill([
            'locked_until' => now()->addMinutes(30),
            'failed_login_attempts' => 5,
        ])->save();

        $this->postJson('/api/v1/portal/login', [
            'email' => 'audit_locked@test.com',
            'password' => 'CorrectPass!1',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'portal_login_locked',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_logout_records_audit_log(): void
    {
        $user = $this->createPortalUser('audit_logout@test.com', 'CorrectPass!1');
        Sanctum::actingAs($user, ['portal:access']);

        $this->postJson('/api/v1/portal/logout')->assertStatus(204);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'portal_logout',
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_login_blocked_when_email_not_verified(): void
    {
        // sec-portal-login-no-email-verification: espelha AuthController web.
        config(['portal.require_email_verified' => true]);

        $user = $this->createPortalUser('unverified@test.com', 'CorrectPass!1');
        $user->forceFill(['email_verified_at' => null])->save();
        $this->createActiveContract();

        $response = $this->postJson('/api/v1/portal/login', [
            'email' => 'unverified@test.com',
            'password' => 'CorrectPass!1',
        ]);

        $response->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_login_allows_verified_email_when_flag_on(): void
    {
        config(['portal.require_email_verified' => true]);

        $user = $this->createPortalUser('verified@test.com', 'CorrectPass!1');
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->createActiveContract();

        $this->postJson('/api/v1/portal/login', [
            'email' => 'verified@test.com',
            'password' => 'CorrectPass!1',
        ])->assertOk();
    }

    public function test_login_lockout_is_isolated_per_tenant(): void
    {
        // Usuário do tenant A bloqueado não interfere em usuário de tenant B com mesmo email
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        // Usuário A (tenant principal) bloqueado
        $userA = $this->createPortalUser('shared@test.com', 'CorrectPass!1');
        $this->createActiveContract();
        $userA->forceFill([
            'locked_until' => now()->addHours(1),
            'failed_login_attempts' => 5,
        ])->save();

        // Usuário B em outro tenant com mesmo email + contrato ativo.
        // forceCreate porque $fillable de ClientPortalUser não aceita tenant_id/is_active
        // por segurança (sec-18 + data-02).
        $userB = ClientPortalUser::forceCreate([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'name' => 'Portal B',
            'email' => 'unique_b@test.com',
            'password' => Hash::make('CorrectPass!1'),
            'is_active' => true,
            'email_verified_at' => now(),
        ])->refresh();
        Contract::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'status' => 'active',
        ]);

        // Login do B deve funcionar normalmente (lockout do A não vaza)
        $response = $this->postJson('/api/v1/portal/login', [
            'email' => 'unique_b@test.com',
            'password' => 'CorrectPass!1',
        ]);

        $response->assertOk();
    }
}
