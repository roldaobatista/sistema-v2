<?php

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
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

function equipUser(Tenant $tenant, array $permissions = []): User
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
// Equipment - View
// ============================================================

test('user WITH equipments.equipment.view can list equipments', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipments')->assertOk();
});

test('user WITHOUT equipments.equipment.view gets 403 on list equipments', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipments')->assertForbidden();
});

test('user WITH equipments.equipment.view can show equipment', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.view']);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->getJson("/api/v1/equipments/{$equipment->id}")->assertOk();
});

test('user WITHOUT equipments.equipment.view gets 403 on show equipment', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->getJson("/api/v1/equipments/{$equipment->id}")->assertForbidden();
});

test('user WITH equipments.equipment.view can access equipment dashboard', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipments-dashboard')->assertOk();
});

test('user WITHOUT equipments.equipment.view gets 403 on equipment dashboard', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipments-dashboard')->assertForbidden();
});

test('user WITH equipments.equipment.view can access equipment alerts', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipments-alerts')->assertOk();
});

test('user WITHOUT equipments.equipment.view gets 403 on equipment alerts', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipments-alerts')->assertForbidden();
});

test('user WITH equipments.equipment.view can access calibration history', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.view']);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->getJson("/api/v1/equipments/{$equipment->id}/calibrations")->assertOk();
});

test('user WITHOUT equipments.equipment.view gets 403 on calibration history', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->getJson("/api/v1/equipments/{$equipment->id}/calibrations")->assertForbidden();
});

test('user WITH equipments.equipment.view can export equipments CSV', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipments-export')->assertOk();
});

test('user WITHOUT equipments.equipment.view gets 403 on export CSV', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipments-export')->assertForbidden();
});

// ============================================================
// Equipment - Create
// ============================================================

test('user WITH equipments.equipment.create can store equipment', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/equipments', [
        'customer_id' => $this->customer->id,
        'type' => 'balanca_analitica',
        'brand' => 'Mettler Toledo',
        'model' => 'XPR205',
        'serial_number' => 'SN-'.fake()->unique()->numerify('########'),
        'status' => 'active',
    ])->assertStatus(201);
});

test('user WITHOUT equipments.equipment.create gets 403 on store equipment', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/equipments', [
        'customer_id' => $this->customer->id,
        'type' => 'balanca_analitica',
    ])->assertForbidden();
});

test('user WITH equipments.equipment.create can add calibration', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.create']);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->postJson("/api/v1/equipments/{$equipment->id}/calibrations", [
        'calibration_date' => now()->toDateString(),
        'calibration_type' => 'interna',
        'result' => 'aprovado',
        'certificate_number' => 'CERT-001',
    ])->assertStatus(201);
});

test('user WITHOUT equipments.equipment.create gets 403 on add calibration', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->postJson("/api/v1/equipments/{$equipment->id}/calibrations", [
        'calibration_date' => now()->toDateString(),
    ])->assertForbidden();
});

// ============================================================
// Equipment - Update
// ============================================================

test('user WITH equipments.equipment.update can update equipment', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.update']);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->putJson("/api/v1/equipments/{$equipment->id}", [
        'brand' => 'Marca Atualizada',
    ])->assertOk();
});

test('user WITHOUT equipments.equipment.update gets 403 on update equipment', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->putJson("/api/v1/equipments/{$equipment->id}", [
        'brand' => 'Marca Atualizada',
    ])->assertForbidden();
});

// ============================================================
// Equipment - Delete
// ============================================================

test('user WITH equipments.equipment.delete can delete equipment', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.delete']);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->deleteJson("/api/v1/equipments/{$equipment->id}")->assertNoContent();
});

test('user WITHOUT equipments.equipment.delete gets 403 on delete equipment', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->deleteJson("/api/v1/equipments/{$equipment->id}")->assertForbidden();
});

// ============================================================
// Equipment Maintenances
// ============================================================

test('user WITH equipments.equipment.view can list equipment maintenances', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.view']);
    Sanctum::actingAs($user, ['*']);

    $this->withoutExceptionHandling()->getJson('/api/v1/equipment-maintenances')->assertOk();
});

test('user WITHOUT equipments.equipment.view gets 403 on equipment maintenances', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipment-maintenances')->assertForbidden();
});

// ============================================================
// Equipment Models
// ============================================================

test('user WITH equipments.equipment_model.view can list equipment models', function () {
    $user = equipUser($this->tenant, ['equipments.equipment_model.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipment-models')->assertOk();
});

test('user WITHOUT equipments.equipment_model.view gets 403 on equipment models', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/equipment-models')->assertForbidden();
});

test('user WITH equipments.equipment_model.create can store equipment model', function () {
    $user = equipUser($this->tenant, ['equipments.equipment_model.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/equipment-models', [
        'name' => 'Balanca Analitica XPR',
        'brand' => 'Mettler Toledo',
    ])->assertStatus(201);
});

test('user WITHOUT equipments.equipment_model.create gets 403 on store equipment model', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/equipment-models', [
        'name' => 'Modelo Teste',
    ])->assertForbidden();
});

// ============================================================
// Standard Weights
// ============================================================

test('user WITH equipments.standard_weight.view can list standard weights', function () {
    $user = equipUser($this->tenant, ['equipments.standard_weight.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/standard-weights')->assertOk();
});

test('user WITHOUT equipments.standard_weight.view gets 403 on standard weights', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/standard-weights')->assertForbidden();
});

test('user WITH equipments.standard_weight.create can store standard weight', function () {
    $user = equipUser($this->tenant, ['equipments.standard_weight.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/standard-weights', [
        'code' => 'PW-001',
        'nominal_value' => 1000,
        'unit' => 'g',
        'class' => 'F1',
    ])->assertStatus(201);
});

test('user WITHOUT equipments.standard_weight.create gets 403 on store standard weight', function () {
    $user = equipUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/standard-weights', [
        'code' => 'PW-001',
    ])->assertForbidden();
});

// ============================================================
// Cross-permission checks
// ============================================================

test('user WITH only equipments.equipment.view cannot create equipment', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.view']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/equipments', [
        'customer_id' => $this->customer->id,
        'type' => 'balanca_analitica',
    ])->assertForbidden();
});

test('user WITH only equipments.equipment.view cannot delete equipment', function () {
    $user = equipUser($this->tenant, ['equipments.equipment.view']);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->deleteJson("/api/v1/equipments/{$equipment->id}")->assertForbidden();
});
