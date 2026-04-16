<?php

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->withoutMiddleware([
        EnsureTenantScope::class,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);
    setPermissionsTeamId($this->tenant->id);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
});

function woUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'current_tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    setPermissionsTeamId($tenant->id);

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        $user->givePermissionTo($perm);
    }

    return $user;
}

// ============================================================
// Work Orders - View
// ============================================================

test('user WITH os.work_order.view can list work orders', function () {
    $user = woUser($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertOk();
});

test('user WITHOUT os.work_order.view gets 403 on list work orders', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertForbidden();
});

test('user WITH os.work_order.view can show a work order', function () {
    $user = woUser($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}")->assertOk();
});

test('user WITHOUT os.work_order.view gets 403 on show work order', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}")->assertForbidden();
});

test('user WITH os.work_order.view can access work orders metadata', function () {
    $user = woUser($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders-metadata')->assertOk();
});

test('user WITHOUT os.work_order.view gets 403 on work orders metadata', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders-metadata')->assertForbidden();
});

test('user WITH os.work_order.view can access dashboard stats', function () {
    $user = woUser($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders-dashboard-stats')->assertOk();
});

test('user WITHOUT os.work_order.view gets 403 on dashboard stats', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders-dashboard-stats')->assertForbidden();
});

// ============================================================
// Work Orders - Create
// ============================================================

test('user WITH os.work_order.create can store work order', function () {
    $user = woUser($this->tenant, ['os.work_order.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'Calibracao de balanca',
        'priority' => 'normal',
    ])->assertStatus(201);
});

test('user WITHOUT os.work_order.create gets 403 on store work order', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'Calibracao de balanca',
    ])->assertForbidden();
});

test('user WITH os.work_order.create can duplicate work order', function () {
    $user = woUser($this->tenant, ['os.work_order.create']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/work-orders/{$wo->id}/duplicate")->assertStatus(201);
});

test('user WITHOUT os.work_order.create gets 403 on duplicate work order', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/work-orders/{$wo->id}/duplicate")->assertForbidden();
});

// ============================================================
// Work Orders - Update
// ============================================================

test('user WITH os.work_order.update can update work order', function () {
    $user = woUser($this->tenant, ['os.work_order.update']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->putJson("/api/v1/work-orders/{$wo->id}", [
        'description' => 'Calibracao atualizada',
    ])->assertOk();
});

test('user WITHOUT os.work_order.update gets 403 on update work order', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->putJson("/api/v1/work-orders/{$wo->id}", [
        'description' => 'Calibracao atualizada',
    ])->assertForbidden();
});

test('user WITH os.work_order.update can store attachment', function () {
    $user = woUser($this->tenant, ['os.work_order.update']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/work-orders/{$wo->id}/attachments", [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertSuccessful();
});

test('user WITHOUT os.work_order.update gets 403 on store attachment', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/work-orders/{$wo->id}/attachments", [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertForbidden();
});

// ============================================================
// Work Orders - Delete
// ============================================================

test('user WITH os.work_order.delete can delete work order', function () {
    $user = woUser($this->tenant, ['os.work_order.delete']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->deleteJson("/api/v1/work-orders/{$wo->id}")->assertNoContent();
});

test('user WITHOUT os.work_order.delete gets 403 on delete work order', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->deleteJson("/api/v1/work-orders/{$wo->id}")->assertForbidden();
});

// ============================================================
// Work Orders - Status Changes
// ============================================================

test('user WITH os.work_order.change_status can update work order status', function () {
    $user = woUser($this->tenant, ['os.work_order.change_status']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => 'in_progress',
    ])->assertOk();
});

test('user WITHOUT os.work_order.change_status gets 403 on status update', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->putJson("/api/v1/work-orders/{$wo->id}/status", [
        'status' => 'in_progress',
    ])->assertForbidden();
});

test('user WITH os.work_order.change_status can reopen work order', function () {
    $user = woUser($this->tenant, ['os.work_order.change_status']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
        'status' => 'cancelled',
    ]);

    $this->postJson("/api/v1/work-orders/{$wo->id}/reopen")->assertOk();
});

test('user WITHOUT os.work_order.change_status gets 403 on reopen', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
        'status' => 'cancelled',
    ]);

    $this->postJson("/api/v1/work-orders/{$wo->id}/reopen")->assertForbidden();
});

// ============================================================
// Work Orders - Export
// ============================================================

test('user WITH os.work_order.export can export work orders', function () {
    $user = woUser($this->tenant, ['os.work_order.export']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders-export')->assertOk();
});

test('user WITHOUT os.work_order.export gets 403 on export work orders', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders-export')->assertForbidden();
});

// ============================================================
// Work Orders - Dispatch Authorization
// ============================================================

test('user WITH os.work_order.authorize_dispatch can authorize dispatch', function () {
    $user = woUser($this->tenant, ['os.work_order.authorize_dispatch']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/work-orders/{$wo->id}/authorize-dispatch")->assertOk();
});

test('user WITHOUT os.work_order.authorize_dispatch gets 403', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/work-orders/{$wo->id}/authorize-dispatch")->assertForbidden();
});

// ============================================================
// Work Orders - Chat
// ============================================================

test('user WITH os.work_order.view can list work order chats', function () {
    $user = woUser($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}/chats")->assertOk();
});

test('user WITHOUT os.work_order.view gets 403 on work order chats', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}/chats")->assertForbidden();
});

test('user WITH os.work_order.update can post chat message', function () {
    $user = woUser($this->tenant, ['os.work_order.update']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/work-orders/{$wo->id}/chats", [
        'message' => 'Equipamento pronto para retirada',
    ])->assertStatus(201);
});

test('user WITHOUT os.work_order.update gets 403 on post chat message', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/work-orders/{$wo->id}/chats", [
        'message' => 'Equipamento pronto',
    ])->assertForbidden();
});

// ============================================================
// Work Orders - Approvals
// ============================================================

test('user WITH os.work_order.view can list work order approvals', function () {
    $user = woUser($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}/approvals")->assertOk();
});

test('user WITHOUT os.work_order.view gets 403 on work order approvals', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}/approvals")->assertForbidden();
});

// ============================================================
// Work Orders - Audit Trail
// ============================================================

test('user WITH os.work_order.view can access audit trail', function () {
    $user = woUser($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}/audit-trail")->assertOk();
});

test('user WITHOUT os.work_order.view gets 403 on audit trail', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}/audit-trail")->assertForbidden();
});

// ============================================================
// Work Orders - PDF
// ============================================================

test('user WITH os.work_order.view can download work order PDF', function () {
    $user = woUser($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}/pdf")->assertSuccessful();
});

test('user WITHOUT os.work_order.view gets 403 on work order PDF', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}/pdf")->assertForbidden();
});

// ============================================================
// Work Orders - Checklist Responses
// ============================================================

test('user WITH os.work_order.view can list checklist responses', function () {
    $user = woUser($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}/checklist-responses")->assertOk();
});

test('user WITHOUT os.work_order.view gets 403 on checklist responses', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}/checklist-responses")->assertForbidden();
});

// ============================================================
// Work Orders - Cost Estimate
// ============================================================

test('user WITH os.work_order.view can access cost estimate', function () {
    $user = woUser($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}/cost-estimate")->assertOk();
});

test('user WITHOUT os.work_order.view gets 403 on cost estimate', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/work-orders/{$wo->id}/cost-estimate")->assertForbidden();
});

// ============================================================
// Parts Kits
// ============================================================

test('user WITH os.work_order.view and os.checklist.view can list parts kits', function () {
    $user = woUser($this->tenant, ['os.work_order.view', 'os.checklist.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/parts-kits')->assertOk();
});

test('user WITHOUT os.checklist.view gets 403 on parts kits', function () {
    $user = woUser($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/parts-kits')->assertForbidden();
});

// ============================================================
// Recurring Contracts
// ============================================================

test('user WITH os.work_order.view can list recurring contracts', function () {
    $user = woUser($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/recurring-contracts')->assertOk();
});

test('user WITHOUT os.work_order.view gets 403 on recurring contracts', function () {
    $user = woUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/recurring-contracts')->assertForbidden();
});
