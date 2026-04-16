<?php

use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayableCategory;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->withoutMiddleware([
        EnsureTenantScope::class,
    ]);

    $this->tenant = Tenant::factory()->create();
    $this->category = AccountPayableCategory::factory()->create(['tenant_id' => $this->tenant->id]);
    app()->instance('current_tenant_id', $this->tenant->id);
    setPermissionsTeamId($this->tenant->id);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
});

function roleUser(Tenant $tenant, string $roleName, array $permissions = []): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'current_tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    setPermissionsTeamId($tenant->id);

    $role = Role::create([
        'name' => $roleName,
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);

    $user->assignRole($role);

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
    }

    return $user;
}

// ============================================================
// Super Admin - bypasses all permission checks
// ============================================================

test('super_admin can access financial endpoints', function () {
    $user = roleUser($this->tenant, Role::SUPER_ADMIN);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable')->assertOk();
});

test('super_admin can access work orders', function () {
    $user = roleUser($this->tenant, Role::SUPER_ADMIN);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertOk();
});

test('super_admin can access CRM deals', function () {
    $user = roleUser($this->tenant, Role::SUPER_ADMIN);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/deals')->assertOk();
});

test('super_admin can access customers', function () {
    $user = roleUser($this->tenant, Role::SUPER_ADMIN);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/customers')->assertOk();
});

test('super_admin can access stock movements', function () {
    $user = roleUser($this->tenant, Role::SUPER_ADMIN);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/movements')->assertOk();
});

test('super_admin can access equipments', function () {
    $user = roleUser($this->tenant, Role::SUPER_ADMIN);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipments')->assertOk();
});

test('super_admin can access quotes', function () {
    $user = roleUser($this->tenant, Role::SUPER_ADMIN);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/quotes')->assertOk();
});

test('super_admin can access HR', function () {
    $user = roleUser($this->tenant, Role::SUPER_ADMIN);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/dashboard')->assertOk();
});

test('super_admin can delete work order without explicit permission', function () {
    $user = roleUser($this->tenant, Role::SUPER_ADMIN);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->deleteJson("/api/v1/work-orders/{$wo->id}")->assertNoContent();
});

test('super_admin can create customer without explicit permission', function () {
    $user = roleUser($this->tenant, Role::SUPER_ADMIN);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/customers', [
        'name' => 'Novo Cliente SA',
        'type' => 'PJ',
        'email' => 'novo@empresa.com',
    ])->assertStatus(201);
});

// ============================================================
// Gerente - financial + OS + CRM access
// ============================================================

test('gerente with financial permissions can access accounts payable', function () {
    $user = roleUser($this->tenant, Role::GERENTE, [
        'finance.payable.view',
        'finance.receivable.view',
        'os.work_order.view',
        'crm.deal.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable')->assertOk();
});

test('gerente with OS permissions can access work orders', function () {
    $user = roleUser($this->tenant, Role::GERENTE, [
        'os.work_order.view',
        'os.work_order.create',
        'os.work_order.update',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertOk();
});

test('gerente with CRM permissions can access CRM', function () {
    $user = roleUser($this->tenant, Role::GERENTE, [
        'crm.deal.view',
        'crm.deal.create',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/deals')->assertOk();
});

test('gerente without HR permissions cannot access HR', function () {
    $user = roleUser($this->tenant, Role::GERENTE, [
        'finance.payable.view',
        'os.work_order.view',
        'crm.deal.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/dashboard')->assertForbidden();
});

test('gerente with financial permissions can access receivables', function () {
    $user = roleUser($this->tenant, Role::GERENTE, [
        'finance.receivable.view',
        'finance.receivable.create',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-receivable')->assertOk();
});

// ============================================================
// Tecnico - limited to own work orders
// ============================================================

test('tecnico with OS view can list work orders', function () {
    $user = roleUser($this->tenant, Role::TECNICO, [
        'os.work_order.view',
        'os.work_order.change_status',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertOk();
});

test('tecnico without financial permissions cannot access payables', function () {
    $user = roleUser($this->tenant, Role::TECNICO, [
        'os.work_order.view',
        'os.work_order.change_status',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable')->assertForbidden();
});

test('tecnico without CRM permissions cannot access CRM', function () {
    $user = roleUser($this->tenant, Role::TECNICO, [
        'os.work_order.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/deals')->assertForbidden();
});

test('tecnico without quote permissions cannot access quotes', function () {
    $user = roleUser($this->tenant, Role::TECNICO, [
        'os.work_order.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/quotes')->assertForbidden();
});

test('tecnico without stock permissions cannot access stock', function () {
    $user = roleUser($this->tenant, Role::TECNICO, [
        'os.work_order.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/movements')->assertForbidden();
});

test('tecnico can change work order status', function () {
    $user = roleUser($this->tenant, Role::TECNICO, [
        'os.work_order.view',
        'os.work_order.change_status',
    ]);
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

test('tecnico without delete permission cannot delete work order', function () {
    $user = roleUser($this->tenant, Role::TECNICO, [
        'os.work_order.view',
        'os.work_order.change_status',
    ]);
    Sanctum::actingAs($user, ['*']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $user->id,
    ]);

    $this->deleteJson("/api/v1/work-orders/{$wo->id}")->assertForbidden();
});

// ============================================================
// Vendedor - CRM + quotes only
// ============================================================

test('vendedor with CRM permissions can access CRM deals', function () {
    $user = roleUser($this->tenant, Role::VENDEDOR, [
        'crm.deal.view',
        'crm.deal.create',
        'crm.deal.update',
        'quotes.quote.view',
        'quotes.quote.create',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/deals')->assertOk();
});

test('vendedor with quote permissions can list quotes', function () {
    $user = roleUser($this->tenant, Role::VENDEDOR, [
        'crm.deal.view',
        'quotes.quote.view',
        'quotes.quote.create',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/quotes')->assertOk();
});

test('vendedor with quote permissions can create quote', function () {
    $user = roleUser($this->tenant, Role::VENDEDOR, [
        'crm.deal.view',
        'quotes.quote.view',
        'quotes.quote.create',
    ]);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->postJson('/api/v1/quotes', [
        'customer_id' => $this->customer->id,
        'valid_until' => now()->addDays(7)->toDateString(),
        'equipments' => [[
            'equipment_id' => $equipment->id,
            'items' => [[
                'type' => 'product',
                'product_id' => $product->id,
                'quantity' => 1,
                'original_price' => 100,
                'unit_price' => 100,
            ]],
        ]],
    ])->assertStatus(201);
});

test('vendedor without financial permissions cannot access payables', function () {
    $user = roleUser($this->tenant, Role::VENDEDOR, [
        'crm.deal.view',
        'quotes.quote.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable')->assertForbidden();
});

test('vendedor without OS permissions cannot access work orders', function () {
    $user = roleUser($this->tenant, Role::VENDEDOR, [
        'crm.deal.view',
        'quotes.quote.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertForbidden();
});

test('vendedor without stock permissions cannot access stock', function () {
    $user = roleUser($this->tenant, Role::VENDEDOR, [
        'crm.deal.view',
        'quotes.quote.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/movements')->assertForbidden();
});

test('vendedor without HR permissions cannot access HR', function () {
    $user = roleUser($this->tenant, Role::VENDEDOR, [
        'crm.deal.view',
        'quotes.quote.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/dashboard')->assertForbidden();
});

test('vendedor without equipment permissions cannot access equipments', function () {
    $user = roleUser($this->tenant, Role::VENDEDOR, [
        'crm.deal.view',
        'quotes.quote.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipments')->assertForbidden();
});

// ============================================================
// Financeiro - financial only
// ============================================================

test('financeiro with financial permissions can access payables', function () {
    $user = roleUser($this->tenant, Role::FINANCEIRO, [
        'finance.payable.view',
        'finance.payable.create',
        'finance.payable.update',
        'finance.payable.delete',
        'finance.receivable.view',
        'finance.receivable.create',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable')->assertOk();
});

test('financeiro with financial permissions can access receivables', function () {
    $user = roleUser($this->tenant, Role::FINANCEIRO, [
        'finance.payable.view',
        'finance.receivable.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-receivable')->assertOk();
});

test('financeiro can create account payable', function () {
    $user = roleUser($this->tenant, Role::FINANCEIRO, [
        'finance.payable.view',
        'finance.payable.create',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/accounts-payable', [
        'category_id' => $this->category->id,
        'description' => 'Conta de agua',
        'amount' => 120.00,
        'due_date' => now()->addDays(15)->toDateString(),
        'payment_method' => 'boleto',
    ])->assertStatus(201);
});

test('financeiro without OS permissions cannot access work orders', function () {
    $user = roleUser($this->tenant, Role::FINANCEIRO, [
        'finance.payable.view',
        'finance.receivable.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertForbidden();
});

test('financeiro without CRM permissions cannot access CRM', function () {
    $user = roleUser($this->tenant, Role::FINANCEIRO, [
        'finance.payable.view',
        'finance.receivable.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/deals')->assertForbidden();
});

test('financeiro without quote permissions cannot access quotes', function () {
    $user = roleUser($this->tenant, Role::FINANCEIRO, [
        'finance.payable.view',
        'finance.receivable.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/quotes')->assertForbidden();
});

test('financeiro without stock permissions cannot access stock', function () {
    $user = roleUser($this->tenant, Role::FINANCEIRO, [
        'finance.payable.view',
        'finance.receivable.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/movements')->assertForbidden();
});

test('financeiro without equipment permissions cannot access equipments', function () {
    $user = roleUser($this->tenant, Role::FINANCEIRO, [
        'finance.payable.view',
        'finance.receivable.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipments')->assertForbidden();
});

// ============================================================
// Estoquista - stock only
// ============================================================

test('estoquista with stock permissions can access movements', function () {
    $user = roleUser($this->tenant, Role::ESTOQUISTA, [
        'estoque.movement.view',
        'estoque.movement.create',
        'estoque.warehouse.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/stock/movements')->assertOk();
});

test('estoquista with stock permissions can access warehouses', function () {
    $user = roleUser($this->tenant, Role::ESTOQUISTA, [
        'estoque.movement.view',
        'estoque.warehouse.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/warehouses')->assertOk();
});

test('estoquista without financial permissions cannot access payables', function () {
    $user = roleUser($this->tenant, Role::ESTOQUISTA, [
        'estoque.movement.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable')->assertForbidden();
});

test('estoquista without OS permissions cannot access work orders', function () {
    $user = roleUser($this->tenant, Role::ESTOQUISTA, [
        'estoque.movement.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertForbidden();
});

// ============================================================
// RH - HR only
// ============================================================

test('rh with HR permissions can access HR dashboard', function () {
    $user = roleUser($this->tenant, Role::RH, [
        'hr.schedule.view',
        'hr.clock.view',
        'hr.leave.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/dashboard')->assertOk();
});

test('rh with HR permissions can access leaves', function () {
    $user = roleUser($this->tenant, Role::RH, [
        'hr.schedule.view',
        'hr.clock.view',
        'hr.leave.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/leaves')->assertOk();
});

test('rh without financial permissions cannot access payables', function () {
    $user = roleUser($this->tenant, Role::RH, [
        'hr.schedule.view',
        'hr.clock.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable')->assertForbidden();
});

test('rh without OS permissions cannot access work orders', function () {
    $user = roleUser($this->tenant, Role::RH, [
        'hr.schedule.view',
        'hr.clock.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertForbidden();
});

// ============================================================
// Visualizador - read-only access where granted
// ============================================================

test('visualizador with view-only permissions can list but not create', function () {
    $user = roleUser($this->tenant, Role::VISUALIZADOR, [
        'os.work_order.view',
        'finance.payable.view',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertOk();
    $this->getJson('/api/v1/accounts-payable')->assertOk();

    // Cannot create
    $this->postJson('/api/v1/work-orders', [
        'customer_id' => $this->customer->id,
        'description' => 'Teste',
    ])->assertForbidden();

    $this->postJson('/api/v1/accounts-payable', [
        'description' => 'Teste',
        'amount' => 100,
    ])->assertForbidden();
});

test('visualizador without permissions cannot access anything', function () {
    $user = roleUser($this->tenant, Role::VISUALIZADOR, []);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertForbidden();
    $this->getJson('/api/v1/accounts-payable')->assertForbidden();
    $this->getJson('/api/v1/crm/deals')->assertForbidden();
    $this->getJson('/api/v1/customers')->assertForbidden();
});

// ============================================================
// User with no role - denied
// ============================================================

test('user with no role and no permissions is denied everywhere', function () {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertForbidden();
    $this->getJson('/api/v1/accounts-payable')->assertForbidden();
    $this->getJson('/api/v1/crm/deals')->assertForbidden();
    $this->getJson('/api/v1/customers')->assertForbidden();
    $this->getJson('/api/v1/quotes')->assertForbidden();
    $this->getJson('/api/v1/equipments')->assertForbidden();
    $this->getJson('/api/v1/stock/movements')->assertForbidden();
    $this->getJson('/api/v1/hr/dashboard')->assertForbidden();
});

// ============================================================
// Permission isolation between roles
// ============================================================

test('permission from one role does not leak to another user', function () {
    $userWithPerm = roleUser($this->tenant, Role::FINANCEIRO, [
        'finance.payable.view',
    ]);
    $userWithoutPerm = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);

    // User with permission succeeds
    Sanctum::actingAs($userWithPerm, ['*']);
    $this->getJson('/api/v1/accounts-payable')->assertOk();

    // User without permission fails
    Sanctum::actingAs($userWithoutPerm, ['*']);
    $this->getJson('/api/v1/accounts-payable')->assertForbidden();
});

test('adding a permission at runtime grants access', function () {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    Sanctum::actingAs($user, ['*']);

    // Initially denied
    $this->getJson('/api/v1/accounts-payable')->assertForbidden();

    // Grant permission
    setPermissionsTeamId($this->tenant->id);
    Permission::firstOrCreate(['name' => 'finance.payable.view', 'guard_name' => 'web']);
    $user->givePermissionTo('finance.payable.view');

    // Reset cached permissions
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = $user->fresh();
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable')->assertOk();
});
