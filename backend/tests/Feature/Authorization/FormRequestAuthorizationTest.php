<?php

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->withoutMiddleware([EnsureTenantScope::class]);
    Event::fake();

    $this->tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
    app()->instance('current_tenant_id', $this->tenant->id);
    setPermissionsTeamId($this->tenant->id);
});

function createUserWithPermissions(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create([
        'current_tenant_id' => $tenant->id,
        'is_active' => true,
    ]);
    $user->tenant_id = $tenant->id;
    $user->save();
    $user->tenants()->attach($tenant->id, ['is_default' => true]);
    setPermissionsTeamId($tenant->id);

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        $user->givePermissionTo($perm);
    }

    return $user;
}

// ══════════════════════════════════════════
// OS Domain — os.work_order.view
// ══════════════════════════════════════════

test('OS: user WITH os.work_order.view can list work orders', function () {
    $user = createUserWithPermissions($this->tenant, ['os.work_order.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertOk();
});

test('OS: user WITHOUT os.work_order.view gets 403', function () {
    $user = createUserWithPermissions($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-orders')->assertForbidden();
});

// ══════════════════════════════════════════
// Financeiro — finance.payable.view
// ══════════════════════════════════════════

test('Financeiro: user WITH finance.payable.view can list accounts payable', function () {
    $user = createUserWithPermissions($this->tenant, ['finance.payable.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable')->assertOk();
});

test('Financeiro: user WITHOUT finance.payable.view gets 403', function () {
    $user = createUserWithPermissions($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/accounts-payable')->assertForbidden();
});

// ══════════════════════════════════════════
// Estoque — estoque.warehouse.view
// ══════════════════════════════════════════

test('Estoque: user WITH estoque.warehouse.view can list warehouses', function () {
    $user = createUserWithPermissions($this->tenant, ['estoque.warehouse.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/warehouses')->assertOk();
});

test('Estoque: user WITHOUT estoque.warehouse.view gets 403', function () {
    $user = createUserWithPermissions($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/warehouses')->assertForbidden();
});

// ══════════════════════════════════════════
// Clientes — cadastros.customer.view
// ══════════════════════════════════════════

test('Clientes: user WITH cadastros.customer.view can list customers', function () {
    $user = createUserWithPermissions($this->tenant, ['cadastros.customer.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/customers')->assertOk();
});

test('Clientes: user WITHOUT cadastros.customer.view gets 403', function () {
    $user = createUserWithPermissions($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/customers')->assertForbidden();
});

// ══════════════════════════════════════════
// RH — rh.work_schedule.view
// ══════════════════════════════════════════

test('RH: user WITH rh.work_schedule.view can list work schedules', function () {
    $user = createUserWithPermissions($this->tenant, ['rh.work_schedule.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-schedules')->assertOk();
});

test('RH: user WITHOUT rh.work_schedule.view gets 403', function () {
    $user = createUserWithPermissions($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/work-schedules')->assertForbidden();
});

// ══════════════════════════════════════════
// IAM — iam.role.view
// ══════════════════════════════════════════

test('IAM: user WITH iam.role.view can list roles', function () {
    $user = createUserWithPermissions($this->tenant, ['iam.role.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/roles')->assertOk();
});

test('IAM: user WITHOUT iam.role.view gets 403', function () {
    $user = createUserWithPermissions($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/roles')->assertForbidden();
});

// ══════════════════════════════════════════
// Agenda — agenda.item.view
// ══════════════════════════════════════════

test('Agenda: user WITH agenda.item.view can list agenda items', function () {
    $user = createUserWithPermissions($this->tenant, ['agenda.item.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/agenda')->assertOk();
});

test('Agenda: user WITHOUT agenda.item.view gets 403', function () {
    $user = createUserWithPermissions($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/agenda')->assertForbidden();
});

// ══════════════════════════════════════════
// Calibracao — calibration.reading.view
// ══════════════════════════════════════════

test('Calibracao: user WITH calibration.reading.view can list non-conformances', function () {
    $user = createUserWithPermissions($this->tenant, ['calibration.reading.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/metrology/non-conformances')->assertOk();
});

test('Calibracao: user WITHOUT calibration.reading.view gets 403', function () {
    $user = createUserWithPermissions($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/metrology/non-conformances')->assertForbidden();
});
