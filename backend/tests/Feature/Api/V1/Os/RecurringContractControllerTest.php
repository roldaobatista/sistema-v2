<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\RecurringContract;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringContractControllerTest extends TestCase
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

    private function createContract(?int $tenantId = null, ?int $customerId = null): RecurringContract
    {
        return RecurringContract::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'customer_id' => $customerId ?? $this->customer->id,
            'created_by' => $this->user->id,
            'name' => 'Contrato Mensal',
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'next_run_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    public function test_index_returns_only_current_tenant_contracts(): void
    {
        $this->createContract();

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $this->createContract($otherTenant->id, $otherCustomer->id);

        $response = $this->getJson('/api/v1/recurring-contracts');

        $response->assertOk()->assertJsonStructure(['data']);

        foreach ($response->json('data') as $row) {
            $this->assertEquals($this->tenant->id, $row['tenant_id']);
        }
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/recurring-contracts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'name', 'frequency', 'start_date']);
    }

    public function test_store_rejects_invalid_frequency(): void
    {
        $response = $this->postJson('/api/v1/recurring-contracts', [
            'customer_id' => $this->customer->id,
            'name' => 'Contrato',
            'frequency' => 'whenever',
            'start_date' => now()->addDays(1)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['frequency']);
    }

    public function test_store_rejects_customer_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/recurring-contracts', [
            'customer_id' => $foreignCustomer->id,
            'name' => 'Cross tenant attempt',
            'frequency' => 'monthly',
            'start_date' => now()->addDays(1)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_creates_contract_with_tenant_and_created_by(): void
    {
        $response = $this->postJson('/api/v1/recurring-contracts', [
            'customer_id' => $this->customer->id,
            'name' => 'Manutenção Mensal',
            'frequency' => 'monthly',
            'start_date' => now()->addDays(1)->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('recurring_contracts', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'name' => 'Manutenção Mensal',
            'frequency' => 'monthly',
        ]);
    }

    public function test_show_returns_404_for_cross_tenant_contract(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = $this->createContract($otherTenant->id, $otherCustomer->id);

        $response = $this->getJson("/api/v1/recurring-contracts/{$foreign->id}");

        $response->assertStatus(404);
    }
}
