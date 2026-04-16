<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\RecurringContract;
use App\Models\RecurringContractItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringContractTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

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
        $this->otherTenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createContract(array $overrides = [], int $itemCount = 0): RecurringContract
    {
        $contract = RecurringContract::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'start_date' => now(),
            'next_run_date' => now()->addMonth(),
            'frequency' => 'monthly',
            'is_active' => true,
        ], $overrides));

        for ($i = 0; $i < $itemCount; $i++) {
            RecurringContractItem::create([
                'recurring_contract_id' => $contract->id,
                'type' => 'service',
                'description' => "Item de contrato {$i}",
                'quantity' => 1,
                'unit_price' => 100.00,
            ]);
        }

        return $contract;
    }

    // ── INDEX ──

    public function test_index_lists_contracts(): void
    {
        $this->createContract(['name' => 'Contrato A', 'generated_count' => 2]);
        $this->createContract(['name' => 'Contrato B']);

        $response = $this->getJson('/api/v1/recurring-contracts');

        $response->assertOk()
            ->assertJsonPath('data.0.generated_count', 2);
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_index_filters_active_only(): void
    {
        $this->createContract(['name' => 'Ativo', 'is_active' => true]);
        $this->createContract(['name' => 'Inativo', 'is_active' => false]);

        $response = $this->getJson('/api/v1/recurring-contracts?active_only=1');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_index_filters_by_customer_id(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->createContract(['name' => 'Do cliente A', 'customer_id' => $this->customer->id]);
        $this->createContract(['name' => 'Do cliente B', 'customer_id' => $otherCustomer->id]);

        $response = $this->getJson("/api/v1/recurring-contracts?customer_id={$this->customer->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_index_search_by_name(): void
    {
        $this->createContract(['name' => 'Contrato Manutenção Mensal']);
        $this->createContract(['name' => 'Contrato Calibração']);

        $response = $this->getJson('/api/v1/recurring-contracts?search=Manutenção');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_index_tenant_isolation(): void
    {
        $this->createContract(['name' => 'Meu contrato']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        RecurringContract::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/recurring-contracts');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    // ── SHOW ──

    public function test_show_returns_contract_with_relations(): void
    {
        $contract = $this->createContract(['name' => 'Contrato detalhado'], 2);

        $response = $this->getJson("/api/v1/recurring-contracts/{$contract->id}");

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_show_returns_404_for_other_tenant(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherContract = RecurringContract::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/recurring-contracts/{$otherContract->id}");

        $response->assertStatus(404);
    }

    // ── STORE ──

    public function test_store_creates_contract_with_items(): void
    {
        $response = $this->postJson('/api/v1/recurring-contracts', [
            'customer_id' => $this->customer->id,
            'name' => 'Novo Contrato',
            'frequency' => 'monthly',
            'start_date' => '2026-04-01',
            'priority' => 'normal',
            'items' => [
                ['type' => 'service', 'description' => 'Visita mensal', 'quantity' => 1, 'unit_price' => 150.00],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('recurring_contracts', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Novo Contrato',
            'frequency' => 'monthly',
            'created_by' => $this->user->id,
        ]);
        $this->assertDatabaseHas('recurring_contract_items', [
            'description' => 'Visita mensal',
        ]);
    }

    public function test_store_sets_next_run_date_to_start_date(): void
    {
        $response = $this->postJson('/api/v1/recurring-contracts', [
            'customer_id' => $this->customer->id,
            'name' => 'Contrato teste data',
            'frequency' => 'weekly',
            'start_date' => '2026-05-01',
        ]);

        $response->assertStatus(201);
        $contract = RecurringContract::where('name', 'Contrato teste data')->first();
        $this->assertEquals('2026-05-01', $contract->next_run_date->format('Y-m-d'));
    }

    public function test_store_validates_customer_id_required(): void
    {
        $response = $this->postJson('/api/v1/recurring-contracts', [
            'name' => 'Contrato sem cliente',
            'frequency' => 'monthly',
            'start_date' => '2026-04-01',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_validates_frequency(): void
    {
        $response = $this->postJson('/api/v1/recurring-contracts', [
            'customer_id' => $this->customer->id,
            'name' => 'Contrato',
            'frequency' => 'invalid_freq',
            'start_date' => '2026-04-01',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_validates_customer_belongs_to_tenant(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);

        $response = $this->postJson('/api/v1/recurring-contracts', [
            'customer_id' => $otherCustomer->id,
            'name' => 'Contrato cross-tenant',
            'frequency' => 'monthly',
            'start_date' => '2026-04-01',
        ]);

        $response->assertStatus(422);
    }

    // ── UPDATE ──

    public function test_update_contract(): void
    {
        $contract = $this->createContract(['name' => 'Original']);

        $response = $this->putJson("/api/v1/recurring-contracts/{$contract->id}", [
            'name' => 'Atualizado',
            'frequency' => 'quarterly',
        ]);

        $response->assertOk();
        $contract->refresh();
        $this->assertEquals('Atualizado', $contract->name);
        $this->assertEquals('quarterly', $contract->frequency);
    }

    public function test_update_replaces_items(): void
    {
        $contract = $this->createContract([], 3);
        $this->assertCount(3, $contract->items);

        $response = $this->putJson("/api/v1/recurring-contracts/{$contract->id}", [
            'items' => [
                ['type' => 'product', 'description' => 'Item unico', 'quantity' => 1, 'unit_price' => 500],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(1, $contract->fresh()->items);
    }

    public function test_update_returns_404_for_other_tenant(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherContract = RecurringContract::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/recurring-contracts/{$otherContract->id}", [
            'name' => 'Hack',
        ]);

        $response->assertStatus(404);
    }

    // ── DESTROY ──

    public function test_destroy_deletes_contract_and_items(): void
    {
        $contract = $this->createContract([], 2);
        $contractId = $contract->id;

        $response = $this->deleteJson("/api/v1/recurring-contracts/{$contractId}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('recurring_contracts', ['id' => $contractId]);
    }

    public function test_destroy_returns_404_for_other_tenant(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherContract = RecurringContract::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/recurring-contracts/{$otherContract->id}");

        $response->assertStatus(404);
    }

    // ── GENERATE ──

    public function test_generate_creates_work_order(): void
    {
        $contract = $this->createContract([
            'name' => 'Contrato Gerador',
            'next_run_date' => now(),
        ], 2);

        $woBefore = WorkOrder::where('tenant_id', $this->tenant->id)->count();

        $response = $this->postJson("/api/v1/recurring-contracts/{$contract->id}/generate");

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'OS gerada com sucesso']);

        $woAfter = WorkOrder::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals($woBefore + 1, $woAfter);

        // next_run_date should have been advanced
        $contract->refresh();
        $this->assertTrue($contract->next_run_date->gt(now()));
    }

    public function test_generate_increments_generated_count(): void
    {
        $contract = $this->createContract(['generated_count' => 5]);

        $this->postJson("/api/v1/recurring-contracts/{$contract->id}/generate");

        $this->assertEquals(6, $contract->fresh()->generated_count);
    }

    public function test_generate_rejects_inactive_contract(): void
    {
        $contract = $this->createContract(['is_active' => false]);

        $response = $this->postJson("/api/v1/recurring-contracts/{$contract->id}/generate");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Contrato inativo']);
    }

    public function test_generate_creates_wo_with_correct_origin(): void
    {
        $contract = $this->createContract(['name' => 'Contrato Origem']);

        $this->postJson("/api/v1/recurring-contracts/{$contract->id}/generate");

        $wo = WorkOrder::where('recurring_contract_id', $contract->id)->first();
        $this->assertNotNull($wo);
        $this->assertEquals(WorkOrder::ORIGIN_RECURRING, $wo->origin_type);
        $this->assertEquals($this->customer->id, $wo->customer_id);
    }

    public function test_generate_tenant_isolation(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherContract = RecurringContract::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v1/recurring-contracts/{$otherContract->id}/generate");

        $response->assertStatus(404);
    }
}
