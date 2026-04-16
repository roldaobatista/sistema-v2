<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Lookups\SupplierContractPaymentFrequency;
use App\Models\Supplier;
use App\Models\SupplierContract;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialAdvancedLookupCompatibilityTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_supplier_contract_store_accepts_lookup_frequency_slug(): void
    {
        SupplierContractPaymentFrequency::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bimestral',
            'slug' => 'bimestral',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/financial/supplier-contracts', [
            'supplier_id' => $supplier->id,
            'description' => 'Contrato de suporte',
            'start_date' => '2026-03-01',
            'end_date' => '2026-12-31',
            'value' => 1200.55,
            'payment_frequency' => 'bimestral',
            'auto_renew' => true,
            'notes' => 'Validacao lookup',
        ])->assertCreated();

        $this->assertSame('bimestral', $response->json('data.payment_frequency'));
        $this->assertDatabaseHas('supplier_contracts', [
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'payment_frequency' => 'bimestral',
            'notes' => 'Validacao lookup',
        ]);
    }

    public function test_supplier_contract_update_accepts_lookup_frequency_name_and_persists_slug(): void
    {
        SupplierContractPaymentFrequency::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Quinzenal',
            'slug' => 'quinzenal',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $contract = SupplierContract::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'description' => 'Contrato inicial',
            'start_date' => '2026-02-01',
            'end_date' => '2026-11-30',
            'value' => 900,
            'payment_frequency' => 'monthly',
            'auto_renew' => false,
            'status' => 'active',
            'notes' => 'Inicial',
        ]);

        $this->putJson("/api/v1/financial/supplier-contracts/{$contract->id}", [
            'supplier_id' => $supplier->id,
            'description' => 'Contrato atualizado',
            'start_date' => '2026-03-01',
            'end_date' => '2026-12-15',
            'value' => 1500,
            'payment_frequency' => 'Quinzenal',
            'auto_renew' => true,
            'notes' => 'Atualizado',
        ])->assertOk()->assertJsonPath('data.payment_frequency', 'quinzenal');

        $this->assertDatabaseHas('supplier_contracts', [
            'id' => $contract->id,
            'payment_frequency' => 'quinzenal',
            'notes' => 'Atualizado',
        ]);
    }

    public function test_supplier_contract_list_normalizes_legacy_frequency_labels_to_slug(): void
    {
        SupplierContractPaymentFrequency::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Quinzenal',
            'slug' => 'quinzenal',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        SupplierContract::unguarded(function () use ($supplier): void {
            SupplierContract::create([
                'tenant_id' => $this->tenant->id,
                'supplier_id' => $supplier->id,
                'description' => 'Contrato legado',
                'start_date' => '2026-03-01',
                'end_date' => '2026-12-31',
                'value' => 700,
                'payment_frequency' => 'Quinzenal',
                'auto_renew' => false,
                'status' => 'active',
            ]);
        });

        $this->getJson('/api/v1/financial/supplier-contracts')
            ->assertOk()
            ->assertJsonPath('data.0.payment_frequency', 'quinzenal');
    }

    public function test_supplier_contract_delete_is_tenant_scoped(): void
    {
        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $myContract = SupplierContract::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'description' => 'Meu contrato',
            'start_date' => '2026-02-01',
            'end_date' => '2026-06-30',
            'value' => 500,
            'payment_frequency' => 'monthly',
            'auto_renew' => false,
            'status' => 'active',
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherSupplier = Supplier::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);
        $foreignContract = SupplierContract::create([
            'tenant_id' => $otherTenant->id,
            'supplier_id' => $otherSupplier->id,
            'description' => 'Contrato externo',
            'start_date' => '2026-02-01',
            'end_date' => '2026-06-30',
            'value' => 800,
            'payment_frequency' => 'monthly',
            'auto_renew' => false,
            'status' => 'active',
        ]);

        $this->deleteJson("/api/v1/financial/supplier-contracts/{$foreignContract->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('supplier_contracts', ['id' => $foreignContract->id]);

        $this->deleteJson("/api/v1/financial/supplier-contracts/{$myContract->id}")
            ->assertOk();

        $this->assertDatabaseMissing('supplier_contracts', ['id' => $myContract->id]);
    }
}
