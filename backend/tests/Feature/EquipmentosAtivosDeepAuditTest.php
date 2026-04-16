<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentModel;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WarrantyTracking;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentosAtivosDeepAuditTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $tenantB;

    private User $user;

    private Customer $customer;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
    }

    // =========================================================
    //  AUTENTICAÇÃO — 401
    // =========================================================

    public function test_unauthenticated_equipment_models_returns_401(): void
    {
        $this->withMiddleware([EnsureTenantScope::class]);
        $this->getJson('/api/v1/equipment-models')->assertUnauthorized();
    }

    public function test_unauthenticated_warranty_tracking_returns_401(): void
    {
        $this->withMiddleware([EnsureTenantScope::class]);
        $this->getJson('/api/v1/stock-advanced/warranty-tracking')->assertUnauthorized();
    }

    public function test_unauthenticated_equipment_alerts_returns_401(): void
    {
        $this->withMiddleware([EnsureTenantScope::class]);
        $this->getJson('/api/v1/equipments-alerts')->assertUnauthorized();
    }

    // =========================================================
    //  EQUIPMENT MODEL — ISOLAMENTO TENANT
    // =========================================================

    public function test_equipment_models_only_returns_current_tenant(): void
    {
        EquipmentModel::create(['tenant_id' => $this->tenant->id, 'name' => 'Balança A']);
        EquipmentModel::create(['tenant_id' => $this->tenant->id, 'name' => 'Balança B']);
        EquipmentModel::create(['tenant_id' => $this->tenantB->id, 'name' => 'Outro Tenant']);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/equipment-models')->assertOk();

        // BelongsToTenant global scope filters to current tenant only
        $this->assertCount(2, $response->json('data'));
    }

    public function test_equipment_model_show_returns_404_for_other_tenant(): void
    {
        $model = EquipmentModel::create(['tenant_id' => $this->tenantB->id, 'name' => 'Cross-Tenant']);

        Sanctum::actingAs($this->user, ['*']);

        // BelongsToTenant global scope + checkTenant abort(404)
        $this->getJson("/api/v1/equipment-models/{$model->id}")->assertNotFound();
    }

    // =========================================================
    //  EQUIPMENT MODEL — VALIDAÇÃO
    // =========================================================

    public function test_store_equipment_model_validates_required_name(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/equipment-models', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // =========================================================
    //  EQUIPMENT MODEL — HAPPY PATH
    // =========================================================

    public function test_store_equipment_model_creates_successfully(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/v1/equipment-models', [
            'name' => 'Balança Analítica M200',
            'brand' => 'Mettler Toledo',
            'category' => 'balanca_analitica',
        ])->assertCreated();

        $this->assertEquals('Balança Analítica M200', $response->json('data.equipment_model.name'));
        $this->assertEquals('Mettler Toledo', $response->json('data.equipment_model.brand'));

        $this->assertDatabaseHas('equipment_models', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Balança Analítica M200',
            'brand' => 'Mettler Toledo',
        ]);
    }

    public function test_update_equipment_model_returns_404_for_other_tenant(): void
    {
        $model = EquipmentModel::create(['tenant_id' => $this->tenantB->id, 'name' => 'Cross-Tenant Model']);

        Sanctum::actingAs($this->user, ['*']);

        $this->putJson("/api/v1/equipment-models/{$model->id}", [
            'name' => 'Tentativa Cross-Tenant',
        ])->assertNotFound();
    }

    public function test_update_equipment_model_changes_name(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Nome Antigo',
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->putJson("/api/v1/equipment-models/{$model->id}", [
            'name' => 'Nome Novo',
        ])->assertOk();

        $this->assertEquals('Nome Novo', $response->json('data.equipment_model.name'));

        $this->assertDatabaseHas('equipment_models', [
            'id' => $model->id,
            'name' => 'Nome Novo',
        ]);
    }

    public function test_destroy_equipment_model_blocked_with_linked_equipments(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Model com Equipamentos',
        ]);

        // Link an equipment to this model
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'equipment_model_id' => $model->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->deleteJson("/api/v1/equipment-models/{$model->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('equipment_models', ['id' => $model->id]);
    }

    public function test_destroy_equipment_model_deletes_without_equipments(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Model Sem Equipamentos',
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->deleteJson("/api/v1/equipment-models/{$model->id}")->assertOk();

        $this->assertDatabaseMissing('equipment_models', ['id' => $model->id]);
    }

    public function test_destroy_equipment_model_returns_404_for_other_tenant(): void
    {
        $model = EquipmentModel::create(['tenant_id' => $this->tenantB->id, 'name' => 'Cross-Tenant']);

        Sanctum::actingAs($this->user, ['*']);

        $this->deleteJson("/api/v1/equipment-models/{$model->id}")->assertNotFound();
    }

    public function test_sync_products_only_links_same_tenant_products(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Model para Produtos',
        ]);

        // Product from own tenant
        $productA = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        // Product from other tenant — must be silently ignored
        $productB = Product::factory()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->user, ['*']);

        // Cross-tenant product should be rejected by validation
        $this->putJson("/api/v1/equipment-models/{$model->id}/products", [
            'product_ids' => [$productA->id, $productB->id],
        ])->assertUnprocessable();

        // Only same-tenant products should work
        $response = $this->putJson("/api/v1/equipment-models/{$model->id}/products", [
            'product_ids' => [$productA->id],
        ])->assertOk();

        $products = $response->json('data.equipment_model.products');
        $ids = collect($products)->pluck('id')->all();

        $this->assertContains($productA->id, $ids);
        $this->assertNotContains($productB->id, $ids);
    }

    // =========================================================
    //  EQUIPMENT — ALERTS ENDPOINT
    // =========================================================

    public function test_equipment_alerts_returns_expected_structure(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/equipments-alerts')->assertOk();

        $this->assertArrayHasKey('alerts', $response->json('data') ?? $response->json());
    }

    public function test_equipment_alerts_only_returns_current_tenant(): void
    {
        // Equipment overdue (in calibration_due(60) scope) for tenant A
        Equipment::factory(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'next_calibration_at' => now()->addDays(15),
            'calibration_interval_months' => 12,
            'is_active' => true,
            'status' => Equipment::STATUS_ACTIVE,
        ]);
        // Same for tenant B — should NOT appear
        Equipment::factory(2)->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
            'next_calibration_at' => now()->addDays(15),
            'calibration_interval_months' => 12,
            'is_active' => true,
            'status' => Equipment::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/equipments-alerts')->assertOk();

        $this->assertCount(2, $response->json('data.alerts') ?? $response->json('data.alerts'));
    }

    // =========================================================
    //  EQUIPMENT — UPDATE (não coberto em CalibracaoMetrologiaTest)
    // =========================================================

    public function test_update_equipment_changes_status(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Equipment::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->putJson("/api/v1/equipments/{$equipment->id}", [
            'status' => Equipment::STATUS_IN_CALIBRATION,
        ])->assertOk();

        $this->assertEquals(Equipment::STATUS_IN_CALIBRATION, $response->json('data.status'));

        $this->assertDatabaseHas('equipments', [
            'id' => $equipment->id,
            'status' => Equipment::STATUS_IN_CALIBRATION,
        ]);
    }

    public function test_update_equipment_auto_recalculates_next_calibration(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'calibration_interval_months' => 6,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $lastCalib = now()->toDateString();

        $response = $this->putJson("/api/v1/equipments/{$equipment->id}", [
            'last_calibration_at' => $lastCalib,
        ])->assertOk();

        // Should auto-calculate next_calibration_at = lastCalib + 6 months
        $this->assertNotNull($response->json('data.next_calibration_at'));
    }

    // =========================================================
    //  WARRANTY TRACKING — ISOLAMENTO TENANT
    // =========================================================

    public function test_warranty_tracking_only_returns_current_tenant(): void
    {
        WarrantyTracking::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => WorkOrder::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'customer_id' => $this->customer->id,
            'warranty_start_at' => now()->subMonth(),
            'warranty_end_at' => now()->addYear(),
            'warranty_type' => WarrantyTracking::TYPE_SERVICE,
        ]);
        WarrantyTracking::create([
            'tenant_id' => $this->tenantB->id,
            'work_order_id' => WorkOrder::factory()->create(['tenant_id' => $this->tenantB->id])->id,
            'customer_id' => $this->customerB->id,
            'warranty_start_at' => now()->subMonth(),
            'warranty_end_at' => now()->addYear(),
            'warranty_type' => WarrantyTracking::TYPE_PART,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/stock-advanced/warranty-tracking')->assertOk();

        $this->assertCount(1, $response->json('data'));
    }
}
