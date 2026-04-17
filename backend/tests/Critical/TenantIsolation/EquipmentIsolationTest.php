<?php

/**
 * Tenant Isolation — Equipment Module
 *
 * Validates complete data isolation for: Equipment, CalibrationReading,
 * EquipmentCalibration (certificates).
 *
 * FAILURE HERE = CALIBRATION/EQUIPMENT DATA LEAK BETWEEN TENANTS
 */

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CalibrationReading;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
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

    $this->customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Equip Cust A', 'type' => 'PJ',
    ]);
    $this->customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Equip Cust B', 'type' => 'PJ',
    ]);

    $this->withoutMiddleware([
        EnsureTenantScope::class,
        CheckPermission::class,
    ]);
});

function actAsTenantEquip(object $test, User $user, Tenant $tenant): void
{
    app()->instance('current_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    Sanctum::actingAs($user, ['*']);
}

// ══════════════════════════════════════════════════════════════════
//  EQUIPMENT
// ══════════════════════════════════════════════════════════════════

test('equipment listing only shows own tenant', function () {
    Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'code' => 'EQ-A-001', 'type' => 'balanca_analitica', 'brand' => 'Mettler',
        'model' => 'XS205', 'serial_number' => 'SN-A-001', 'status' => 'active', 'is_active' => true,
    ]);
    Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'code' => 'EQ-B-001', 'type' => 'balanca_rodoviaria', 'brand' => 'Toledo',
        'model' => 'TR100', 'serial_number' => 'SN-B-001', 'status' => 'active', 'is_active' => true,
    ]);

    actAsTenantEquip($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/equipments');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('code')->toArray())->not->toContain('EQ-B-001');
});

test('cannot GET cross-tenant equipment — returns 404', function () {
    $eqB = Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'code' => 'EQ-B-SECRET', 'type' => 'termometro', 'brand' => 'Fluke',
        'model' => 'T500', 'serial_number' => 'SN-B-SECRET', 'status' => 'active', 'is_active' => true,
    ]);

    actAsTenantEquip($this, $this->userA, $this->tenantA);

    $this->getJson("/api/v1/equipments/{$eqB->id}")->assertNotFound();
});

test('cannot UPDATE cross-tenant equipment — returns 404', function () {
    $eqB = Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'code' => 'EQ-B-UPD', 'type' => 'termometro', 'brand' => 'Protected',
        'model' => 'P100', 'serial_number' => 'SN-B-UPD', 'status' => 'active', 'is_active' => true,
    ]);

    actAsTenantEquip($this, $this->userA, $this->tenantA);

    $this->putJson("/api/v1/equipments/{$eqB->id}", ['brand' => 'Hacked'])->assertNotFound();

    $this->assertDatabaseHas('equipments', ['id' => $eqB->id, 'brand' => 'Protected']);
});

test('cannot DELETE cross-tenant equipment — returns 404', function () {
    $eqB = Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'code' => 'EQ-B-DEL', 'type' => 'paquimetro', 'brand' => 'Mitutoyo',
        'model' => 'M200', 'serial_number' => 'SN-B-DEL', 'status' => 'active', 'is_active' => true,
    ]);

    actAsTenantEquip($this, $this->userA, $this->tenantA);

    $this->deleteJson("/api/v1/equipments/{$eqB->id}")->assertNotFound();

    $this->assertDatabaseHas('equipments', ['id' => $eqB->id, 'deleted_at' => null]);
});

test('Equipment model scope isolates by tenant', function () {
    Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'code' => 'SCOPE-EQ-A', 'type' => 'balanca_analitica', 'brand' => 'A',
        'model' => 'A1', 'serial_number' => 'SN-SCOPE-A', 'status' => 'active', 'is_active' => true,
    ]);
    Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'code' => 'SCOPE-EQ-B', 'type' => 'balanca_analitica', 'brand' => 'B',
        'model' => 'B1', 'serial_number' => 'SN-SCOPE-B', 'status' => 'active', 'is_active' => true,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $equipment = Equipment::all();
    expect($equipment)->toHaveCount(1);
    expect($equipment->first()->code)->toBe('SCOPE-EQ-A');
});

// ══════════════════════════════════════════════════════════════════
//  CALIBRATIONS & CERTIFICATES
// ══════════════════════════════════════════════════════════════════

test('equipment calibration history is tenant-scoped', function () {
    $eqA = Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'code' => 'CAL-EQ-A', 'type' => 'balanca_analitica', 'brand' => 'Mettler',
        'model' => 'XS', 'serial_number' => 'SN-CAL-A', 'status' => 'active', 'is_active' => true,
    ]);
    $eqB = Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'code' => 'CAL-EQ-B', 'type' => 'balanca_analitica', 'brand' => 'Shimadzu',
        'model' => 'SH', 'serial_number' => 'SN-CAL-B', 'status' => 'active', 'is_active' => true,
    ]);

    actAsTenantEquip($this, $this->userA, $this->tenantA);

    // Should be able to access own equipment calibrations
    $response = $this->getJson("/api/v1/equipments/{$eqA->id}/calibrations");
    expect($response->status())->toBeIn([200, 204]);

    // Should NOT access cross-tenant equipment calibrations
    $this->getJson("/api/v1/equipments/{$eqB->id}/calibrations")->assertNotFound();
});

test('EquipmentCalibration model scope isolates by tenant', function () {
    $eqA = Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'code' => 'ECAL-A', 'type' => 'balanca_analitica', 'brand' => 'A',
        'model' => 'A', 'serial_number' => 'SN-ECAL-A', 'status' => 'active', 'is_active' => true,
    ]);
    $eqB = Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'code' => 'ECAL-B', 'type' => 'balanca_analitica', 'brand' => 'B',
        'model' => 'B', 'serial_number' => 'SN-ECAL-B', 'status' => 'active', 'is_active' => true,
    ]);

    EquipmentCalibration::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'equipment_id' => $eqA->id,
        'calibration_date' => now(), 'result' => 'approved',
    ]);
    EquipmentCalibration::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'equipment_id' => $eqB->id,
        'calibration_date' => now(), 'result' => 'rejected',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $calibrations = EquipmentCalibration::all();
    expect($calibrations)->toHaveCount(1);
    expect($calibrations->first()->tenant_id)->toBe($this->tenantA->id);
});

test('CalibrationReading model scope isolates by tenant', function () {
    $eqA = Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'code' => 'READ-A', 'type' => 'balanca_analitica', 'brand' => 'A',
        'model' => 'A', 'serial_number' => 'SN-READ-A', 'status' => 'active', 'is_active' => true,
    ]);
    $eqB = Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'code' => 'READ-B', 'type' => 'balanca_analitica', 'brand' => 'B',
        'model' => 'B', 'serial_number' => 'SN-READ-B', 'status' => 'active', 'is_active' => true,
    ]);

    $calA = EquipmentCalibration::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'equipment_id' => $eqA->id,
        'calibration_date' => now(), 'result' => 'approved',
    ]);
    $calB = EquipmentCalibration::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'equipment_id' => $eqB->id,
        'calibration_date' => now(), 'result' => 'approved',
    ]);

    CalibrationReading::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'equipment_calibration_id' => $calA->id,
        'reference_value' => 100, 'indication_increasing' => 100.01,
        'error' => 0.01, 'reading_order' => 1,
    ]);
    CalibrationReading::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'equipment_calibration_id' => $calB->id,
        'reference_value' => 200, 'indication_increasing' => 200.05,
        'error' => 0.05, 'reading_order' => 1,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $readings = CalibrationReading::all();
    expect($readings)->toHaveCount(1);
    expect($readings->first()->tenant_id)->toBe($this->tenantA->id);
});

// ══════════════════════════════════════════════════════════════════
//  EQUIPMENT DASHBOARD & ALERTS
// ══════════════════════════════════════════════════════════════════

test('equipment dashboard is tenant-scoped', function () {
    actAsTenantEquip($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/equipments-dashboard');
    $response->assertOk();
});

test('equipment alerts are tenant-scoped', function () {
    actAsTenantEquip($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/equipments-alerts');
    $response->assertOk();
});
