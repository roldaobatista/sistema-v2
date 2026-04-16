<?php

/**
 * Tenant Isolation — Cascade Delete & Orphan Prevention
 *
 * Validates that deleting a parent record (customer, work order, etc.)
 * properly cascades and does not create orphan records that could leak
 * across tenants.
 *
 * FAILURE HERE = ORPHAN RECORDS POTENTIALLY ACCESSIBLE BY WRONG TENANT
 */

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Model::unguard();
    Model::preventLazyLoading(false);
    Gate::before(fn () => true);

    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->userA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'current_tenant_id' => $this->tenantA->id,
        'is_active' => true,
    ]);

    $this->userB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'current_tenant_id' => $this->tenantB->id,
        'is_active' => true,
    ]);

    $this->withoutMiddleware([
        EnsureTenantScope::class,
        CheckPermission::class,
    ]);
});

function actAsTenantCascade(object $test, User $user, Tenant $tenant): void
{
    app()->instance('current_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    Sanctum::actingAs($user, ['*']);
}

// ══════════════════════════════════════════════════════════════════
//  CUSTOMER SOFT DELETE — RELATED RECORDS REMAIN CORRECTLY SCOPED
// ══════════════════════════════════════════════════════════════════

test('soft deleting customer does not affect other tenant customers', function () {
    $customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Delete Me A', 'type' => 'PJ',
    ]);
    $customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Keep Me B', 'type' => 'PJ',
    ]);

    actAsTenantCascade($this, $this->userA, $this->tenantA);

    $this->deleteJson("/api/v1/customers/{$customerA->id}");

    // Tenant A customer should be soft deleted
    $this->assertSoftDeleted('customers', ['id' => $customerA->id]);

    // Tenant B customer should NOT be affected
    $this->assertDatabaseHas('customers', [
        'id' => $customerB->id,
        'deleted_at' => null,
    ]);
});

test('soft deleting customer — related work orders remain intact', function () {
    $customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Cascade Cust A', 'type' => 'PJ',
    ]);
    $woA = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $customerA->id,
        'created_by' => $this->userA->id, 'number' => 'CASC-WO-A',
        'description' => 'Cascade WO', 'status' => 'completed',
    ]);

    actAsTenantCascade($this, $this->userA, $this->tenantA);

    // The API blocks deletion when dependencies exist (returns 409).
    // Soft-delete the customer directly to test cascade behavior at the DB level.
    $customerA->delete();

    $this->assertSoftDeleted('customers', ['id' => $customerA->id]);

    // Work order should still exist (soft delete should not cascade to WO)
    $this->assertDatabaseHas('work_orders', [
        'id' => $woA->id,
        'tenant_id' => $this->tenantA->id,
    ]);
});

test('soft deleting customer — related accounts receivable remain intact', function () {
    $customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'AR Cascade A', 'type' => 'PJ',
    ]);
    $arA = AccountReceivable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $customerA->id,
        'created_by' => $this->userA->id, 'description' => 'Cascade AR',
        'amount' => 1000, 'due_date' => now()->addDays(30), 'status' => 'pending',
    ]);

    actAsTenantCascade($this, $this->userA, $this->tenantA);

    $this->deleteJson("/api/v1/customers/{$customerA->id}");

    // AR should still exist
    $this->assertDatabaseHas('accounts_receivable', [
        'id' => $arA->id,
        'tenant_id' => $this->tenantA->id,
    ]);
});

// ══════════════════════════════════════════════════════════════════
//  WORK ORDER DELETE — ITEMS CASCADE
// ══════════════════════════════════════════════════════════════════

test('deleting work order does not affect other tenant work orders', function () {
    $customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'WO Del Cust A', 'type' => 'PJ',
    ]);
    $customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'WO Del Cust B', 'type' => 'PJ',
    ]);

    $woA = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $customerA->id,
        'created_by' => $this->userA->id, 'number' => 'DEL-WO-A',
        'description' => 'Delete Me', 'status' => 'open',
    ]);
    $woB = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $customerB->id,
        'created_by' => $this->userB->id, 'number' => 'KEEP-WO-B',
        'description' => 'Keep Me', 'status' => 'open',
    ]);

    actAsTenantCascade($this, $this->userA, $this->tenantA);

    $this->deleteJson("/api/v1/work-orders/{$woA->id}");

    $this->assertSoftDeleted('work_orders', ['id' => $woA->id]);
    $this->assertDatabaseHas('work_orders', [
        'id' => $woB->id,
        'deleted_at' => null,
    ]);
});

test('deleting work order — items stay with correct tenant', function () {
    $customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Item Cascade A', 'type' => 'PJ',
    ]);

    $woA = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $customerA->id,
        'created_by' => $this->userA->id, 'number' => 'ITEM-CASC-A',
        'description' => 'Item Cascade', 'status' => 'open',
    ]);

    $itemA = WorkOrderItem::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'work_order_id' => $woA->id,
        'type' => 'service', 'description' => 'Cascade Item',
        'quantity' => 1, 'unit_price' => 500, 'total' => 500,
    ]);

    actAsTenantCascade($this, $this->userA, $this->tenantA);

    $this->deleteJson("/api/v1/work-orders/{$woA->id}");

    // Item should still have correct tenant_id regardless of cascade
    $this->assertDatabaseHas('work_order_items', [
        'id' => $itemA->id,
        'tenant_id' => $this->tenantA->id,
    ]);
});

// ══════════════════════════════════════════════════════════════════
//  ORPHAN RECORD VERIFICATION
// ══════════════════════════════════════════════════════════════════

test('no orphan work order items exist without parent WO in same tenant', function () {
    $customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Orphan Cust A', 'type' => 'PJ',
    ]);

    $woA = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $customerA->id,
        'created_by' => $this->userA->id, 'number' => 'ORPHAN-WO',
        'description' => 'Orphan Parent', 'status' => 'open',
    ]);

    WorkOrderItem::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'work_order_id' => $woA->id,
        'type' => 'service', 'description' => 'Orphan Item',
        'quantity' => 1, 'unit_price' => 100, 'total' => 100,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    // All items should have a parent WO in the same tenant
    $items = WorkOrderItem::all();
    $items->each(function ($item) {
        $wo = WorkOrder::find($item->work_order_id);
        expect($wo)->not->toBeNull(
            "WorkOrderItem #{$item->id} references WO #{$item->work_order_id} which is not visible in tenant scope"
        );
    });
});

test('CRM deal deletion does not affect other tenant deals', function () {
    $pipeA = CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Casc Pipe A',
        'slug' => 'casc-a-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);
    $stageA = CrmPipelineStage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'pipeline_id' => $pipeA->id,
        'name' => 'Casc Stage A', 'sort_order' => 0, 'probability' => 50,
    ]);
    $pipeB = CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Casc Pipe B',
        'slug' => 'casc-b-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);
    $stageB = CrmPipelineStage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'pipeline_id' => $pipeB->id,
        'name' => 'Casc Stage B', 'sort_order' => 0, 'probability' => 50,
    ]);

    $customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Deal Del Cust A', 'type' => 'PJ',
    ]);
    $customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Deal Del Cust B', 'type' => 'PJ',
    ]);

    $dealA = CrmDeal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $customerA->id,
        'pipeline_id' => $pipeA->id, 'stage_id' => $stageA->id,
        'title' => 'Delete Deal A', 'value' => 1000, 'status' => 'open',
    ]);
    $dealB = CrmDeal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $customerB->id,
        'pipeline_id' => $pipeB->id, 'stage_id' => $stageB->id,
        'title' => 'Keep Deal B', 'value' => 2000, 'status' => 'open',
    ]);

    actAsTenantCascade($this, $this->userA, $this->tenantA);

    $this->deleteJson("/api/v1/crm/deals/{$dealA->id}");

    // Deal B in tenant B should still exist
    $this->assertDatabaseHas('crm_deals', [
        'id' => $dealB->id,
        'tenant_id' => $this->tenantB->id,
    ]);
});

test('equipment deletion does not affect other tenant equipment', function () {
    $customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Eq Del A', 'type' => 'PJ',
    ]);
    $customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Eq Del B', 'type' => 'PJ',
    ]);

    $eqA = Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $customerA->id,
        'code' => 'DEL-EQ-A', 'type' => 'balanca_analitica', 'brand' => 'A',
        'model' => 'A', 'serial_number' => 'SN-DEL-EQ-A', 'status' => 'active', 'is_active' => true,
    ]);
    $eqB = Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $customerB->id,
        'code' => 'KEEP-EQ-B', 'type' => 'balanca_analitica', 'brand' => 'B',
        'model' => 'B', 'serial_number' => 'SN-KEEP-EQ-B', 'status' => 'active', 'is_active' => true,
    ]);

    actAsTenantCascade($this, $this->userA, $this->tenantA);

    $this->deleteJson("/api/v1/equipments/{$eqA->id}");

    $this->assertSoftDeleted('equipments', ['id' => $eqA->id]);
    $this->assertDatabaseHas('equipments', [
        'id' => $eqB->id,
        'deleted_at' => null,
    ]);
});
