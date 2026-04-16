<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for service contracts (ContractController).
 *
 * There is no dedicated ServiceContractController — contracts are managed via
 * ContractController which handles general service/maintenance contracts.
 *
 * Routes (from routes/api/missing-routes.php):
 *   GET    /api/v1/contracts
 *   GET    /api/v1/contracts/{contract}
 *   POST   /api/v1/contracts
 *   PUT    /api/v1/contracts/{contract}
 */
class ServiceContractControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── GET index ──────────────────────────────────────────────

    public function test_can_list_contracts(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Contract::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->getJson('/api/v1/contracts');

        $response->assertStatus(200);
    }

    public function test_listing_only_returns_own_tenant_contracts(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        Contract::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'number' => 'CT-OTHER-01',
        ]);

        $ownCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $ownCustomer->id,
            'number' => 'CT-OWN-01',
        ]);

        $response = $this->getJson('/api/v1/contracts');

        $response->assertStatus(200);
        $json = $response->json();
        $data = isset($json['data']) ? $json['data'] : $json;
        $numbers = collect($data)->pluck('number')->toArray();
        $this->assertContains('CT-OWN-01', $numbers);
        $this->assertNotContains('CT-OTHER-01', $numbers);
    }

    // ── POST store ─────────────────────────────────────────────

    public function test_can_create_contract(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/contracts', [
            'customer_id' => $customer->id,
            'name' => 'Contrato de Manutenção Anual',
            'number' => 'CT-TEST-001',
            'status' => 'active',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['name' => 'Contrato de Manutenção Anual']);

        $this->assertDatabaseHas('contracts', [
            'tenant_id' => $this->tenant->id,
            'number' => 'CT-TEST-001',
        ]);
    }

    public function test_store_requires_customer_id(): void
    {
        $response = $this->postJson('/api/v1/contracts', [
            'name' => 'Contrato sem cliente',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_end_date_must_be_after_start_date(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/contracts', [
            'customer_id' => $customer->id,
            'name' => 'Contrato datas inválidas',
            'start_date' => now()->addYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'), // end before start
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    // ── GET show ───────────────────────────────────────────────

    public function test_can_show_contract(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'number' => 'CT-SHOW-001',
        ]);

        $response = $this->getJson("/api/v1/contracts/{$contract->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['number' => 'CT-SHOW-001']);
    }

    public function test_show_returns_404_for_other_tenant_contract(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $contract = Contract::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->getJson("/api/v1/contracts/{$contract->id}");

        $response->assertStatus(404);
    }

    // ── PUT update ─────────────────────────────────────────────

    public function test_can_update_contract(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'active',
        ]);

        $response = $this->putJson("/api/v1/contracts/{$contract->id}", [
            'status' => 'expired',
            'name' => 'Contrato Atualizado',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => 'expired',
            'name' => 'Contrato Atualizado',
        ]);
    }
}
