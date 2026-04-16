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

    private function createPortalUser(string $email = 'portal@cliente.com', string $password = 'PortalPass!123', bool $active = true): ClientPortalUser
    {
        return ClientPortalUser::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Portal User',
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => $active,
        ]);
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
}
