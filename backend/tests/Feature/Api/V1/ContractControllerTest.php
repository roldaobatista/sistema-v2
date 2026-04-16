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

class ContractControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createContract(?int $tenantId = null, ?int $customerId = null): Contract
    {
        return Contract::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'customer_id' => $customerId ?? $this->customer->id,
            'name' => 'Contrato Teste',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_index_returns_only_current_tenant_contracts(): void
    {
        $this->createContract();

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $this->createContract($otherTenant->id, $otherCustomer->id);

        $response = $this->getJson('/api/v1/contracts');

        $response->assertOk()->assertJsonStructure(['data']);

        foreach ($response->json('data') as $row) {
            $this->assertEquals($this->tenant->id, $row['tenant_id']);
        }
    }

    public function test_store_validates_required_customer(): void
    {
        $response = $this->postJson('/api/v1/contracts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_rejects_customer_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/contracts', [
            'customer_id' => $foreignCustomer->id,
            'name' => 'Cross-tenant attempt',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_rejects_end_date_before_start_date(): void
    {
        $response = $this->postJson('/api/v1/contracts', [
            'customer_id' => $this->customer->id,
            'name' => 'Data errada',
            'start_date' => '2026-06-01',
            'end_date' => '2026-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_store_creates_contract_with_tenant(): void
    {
        $response = $this->postJson('/api/v1/contracts', [
            'customer_id' => $this->customer->id,
            'name' => 'Contrato Anual',
            'description' => 'Contrato de manutenção preventiva',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('contracts', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Contrato Anual',
        ]);
    }

    public function test_show_returns_404_for_cross_tenant_contract(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = $this->createContract($otherTenant->id, $otherCustomer->id);

        $response = $this->getJson("/api/v1/contracts/{$foreign->id}");

        $response->assertStatus(404);
    }
}
