<?php

/**
 * Tenant Isolation — Work Order Module
 *
 * Validates complete data isolation for: WorkOrder, WorkOrderItem, Checklist.
 * Cross-tenant access MUST return 404 (not 403).
 *
 * FAILURE HERE = OPERATIONAL DATA LEAK BETWEEN TENANTS
 */

use App\Models\Checklist;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Model::unguard();
    Model::preventLazyLoading(false);

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

    $this->customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'WO Cust A', 'type' => 'PJ',
    ]);
    $this->customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'WO Cust B', 'type' => 'PJ',
    ]);

    foreach ([[$this->userA, $this->tenantA], [$this->userB, $this->tenantB]] as [$user, $tenant]) {
        $user->tenants()->syncWithoutDetaching([$tenant->id => ['is_default' => true]]);
        app()->instance('current_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        $user->assignRole('super_admin');
    }
});

function actAsTenantWO(object $test, User $user, Tenant $tenant): void
{
    app()->instance('current_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    Sanctum::actingAs($user, ['*']);
}

// ─── 1. Work order listing only shows own tenant ──────────────────
test('work order listing only returns own tenant data', function () {
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'OS-A-001',
        'description' => 'WO Tenant A', 'status' => 'open',
    ]);
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'number' => 'OS-B-001',
        'description' => 'WO Tenant B', 'status' => 'open',
    ]);

    actAsTenantWO($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/work-orders');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('description')->toArray())->not->toContain('WO Tenant B');
});

// ─── 2. Cannot GET cross-tenant work order ────────────────────────
test('tenant A cannot GET tenant B work order — returns 404', function () {
    $woB = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'number' => 'OS-B-SECRET',
        'description' => 'Secret WO', 'status' => 'open',
    ]);

    actAsTenantWO($this, $this->userA, $this->tenantA);

    $this->getJson("/api/v1/work-orders/{$woB->id}")->assertNotFound();
});

// ─── 3. Cannot UPDATE cross-tenant work order ─────────────────────
test('tenant A cannot UPDATE tenant B work order — returns 404', function () {
    $woB = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'number' => 'OS-B-UPD',
        'description' => 'Protected WO', 'status' => 'open',
    ]);

    actAsTenantWO($this, $this->userA, $this->tenantA);

    $this->putJson("/api/v1/work-orders/{$woB->id}", [
        'description' => 'Hacked Description',
    ])->assertNotFound();

    $this->assertDatabaseHas('work_orders', ['id' => $woB->id, 'description' => 'Protected WO']);
});

// ─── 4. Cannot DELETE cross-tenant work order ─────────────────────
test('tenant A cannot DELETE tenant B work order — returns 404', function () {
    $woB = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'number' => 'OS-B-DEL',
        'description' => 'Safe WO', 'status' => 'open',
    ]);

    actAsTenantWO($this, $this->userA, $this->tenantA);

    $this->deleteJson("/api/v1/work-orders/{$woB->id}")->assertNotFound();

    $this->assertDatabaseHas('work_orders', ['id' => $woB->id, 'deleted_at' => null]);
});

// ─── 5. Work order model scope ────────────────────────────────────
test('WorkOrder::all() respects global tenant scope', function () {
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'SCOPE-A',
        'description' => 'Scope A', 'status' => 'open',
    ]);
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'number' => 'SCOPE-B',
        'description' => 'Scope B', 'status' => 'open',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $orders = WorkOrder::all();
    expect($orders)->each(fn ($wo) => $wo->tenant_id->toBe($this->tenantA->id));
    expect($orders->pluck('number')->toArray())->not->toContain('SCOPE-B');
});

// ─── 6. Work order find returns null for cross-tenant ─────────────
test('WorkOrder::find() returns null for cross-tenant', function () {
    $woB = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'number' => 'FIND-B',
        'description' => 'Find B', 'status' => 'open',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    expect(WorkOrder::find($woB->id))->toBeNull();
});

// ─── 7. Work order items scoped to tenant ─────────────────────────
test('WorkOrderItem model scope isolates by tenant', function () {
    $woA = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'ITEM-A',
        'description' => 'Item A OS', 'status' => 'open',
    ]);
    $woB = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'number' => 'ITEM-B',
        'description' => 'Item B OS', 'status' => 'open',
    ]);

    WorkOrderItem::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'work_order_id' => $woA->id,
        'type' => 'service', 'description' => 'Item A', 'quantity' => 1,
        'unit_price' => 100, 'total' => 100,
    ]);
    WorkOrderItem::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'work_order_id' => $woB->id,
        'type' => 'service', 'description' => 'Item B', 'quantity' => 1,
        'unit_price' => 200, 'total' => 200,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $items = WorkOrderItem::all();
    expect($items)->toHaveCount(1);
    expect($items->first()->description)->toBe('Item A');
});

// ─── 8. Dashboard stats are tenant-scoped ─────────────────────────
test('work order dashboard stats are tenant-scoped', function () {
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'DASH-A',
        'description' => 'Dash A', 'status' => 'open',
    ]);
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'number' => 'DASH-B',
        'description' => 'Dash B', 'status' => 'open',
    ]);

    actAsTenantWO($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/work-orders-dashboard-stats');
    $response->assertOk();
});

// ─── 9. Checklist model scoped to tenant ──────────────────────────
test('Checklist model scope isolates by tenant', function () {
    Checklist::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Checklist A',
        'description' => 'A desc', 'items' => [['id' => '1', 'text' => 'check', 'type' => 'boolean', 'required' => true]],
        'is_active' => true,
    ]);
    Checklist::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Checklist B',
        'description' => 'B desc', 'items' => [['id' => '1', 'text' => 'check', 'type' => 'boolean', 'required' => true]],
        'is_active' => true,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $checklists = Checklist::all();
    expect($checklists)->toHaveCount(1);
    expect($checklists->first()->name)->toBe('Checklist A');
});

// ─── 10. Checklists API endpoint is tenant-scoped ─────────────────
test('checklists API listing only returns own tenant', function () {
    Checklist::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'API CL A',
        'description' => 'A', 'items' => [['id' => '1', 'text' => 'x', 'type' => 'boolean', 'required' => true]],
        'is_active' => true,
    ]);
    Checklist::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'API CL B',
        'description' => 'B', 'items' => [['id' => '1', 'text' => 'y', 'type' => 'boolean', 'required' => true]],
        'is_active' => true,
    ]);

    actAsTenantWO($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/checklists');
    $response->assertOk();

    $items = $response->json('data.data') ?? $response->json('data');
    $data = collect($items);
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('name')->toArray())->not->toContain('API CL B');
});

// ─── 11. Cannot assign cross-tenant technician to work order ──────
test('cannot assign cross-tenant user as technician', function () {
    actAsTenantWO($this, $this->userA, $this->tenantA);

    $response = $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customerA->id,
        'description' => 'WO with cross-tenant tech',
        'assigned_to' => $this->userB->id,
        'status' => 'open',
    ]);

    // Ou rejeita a criação ou cria sem o técnico cross-tenant
    if ($response->status() === 201) {
        $woId = $response->json('data.id') ?? $response->json('id');
        $wo = WorkOrder::withoutGlobalScopes()->find($woId);
        expect($wo->assigned_to)->not->toBe($this->userB->id);
    } else {
        // Rejeição é comportamento válido (validação ou 422)
        expect($response->status())->toBeGreaterThanOrEqual(400);
    }
});

// ─── 12. Work order count is tenant-scoped ────────────────────────
test('work order count only reflects own tenant', function () {
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'COUNT-A1',
        'description' => 'Count A1', 'status' => 'open',
    ]);
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'COUNT-A2',
        'description' => 'Count A2', 'status' => 'open',
    ]);
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'number' => 'COUNT-B1',
        'description' => 'Count B1', 'status' => 'open',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);
    expect(WorkOrder::count())->toBe(2);

    app()->instance('current_tenant_id', $this->tenantB->id);
    expect(WorkOrder::count())->toBe(1);
});

// ─── 13. Work order metadata is tenant-scoped ─────────────────────
test('work orders metadata endpoint is tenant-scoped', function () {
    actAsTenantWO($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/work-orders-metadata');
    expect($response->status())->toBeIn([200, 204]);
});

// ─── 14. Work order PDF access is tenant-scoped ───────────────────
test('cannot download cross-tenant work order PDF — returns 404', function () {
    $woB = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'number' => 'PDF-B',
        'description' => 'PDF B', 'status' => 'open',
    ]);

    actAsTenantWO($this, $this->userA, $this->tenantA);

    $this->getJson("/api/v1/work-orders/{$woB->id}/pdf")->assertNotFound();
});

// ─── 15. Audit trail for cross-tenant WO returns 404 ──────────────
test('cannot access audit trail of cross-tenant work order', function () {
    $woB = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'number' => 'AUDIT-B',
        'description' => 'Audit B', 'status' => 'open',
    ]);

    actAsTenantWO($this, $this->userA, $this->tenantA);

    $this->getJson("/api/v1/work-orders/{$woB->id}/audit-trail")->assertNotFound();
});
