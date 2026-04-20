<?php

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
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
});

function hrUser(Tenant $tenant, array $permissions = []): User
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
// Clock - View
// ============================================================

test('user WITH hr.clock.view can access own clock history', function () {
    $user = hrUser($this->tenant, ['hr.clock.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/clock/my')->assertOk();
});

test('user WITHOUT hr.clock.view gets 403 on clock history', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/clock/my')->assertForbidden();
});

test('user WITH hr.clock.view can access all clock entries', function () {
    $user = hrUser($this->tenant, ['hr.clock.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/clock/all')->assertOk();
});

test('user WITHOUT hr.clock.view gets 403 on all clock entries', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/clock/all')->assertForbidden();
});

// ============================================================
// Clock - Manage
// ============================================================

test('user WITH hr.clock.manage can clock in', function () {
    $user = hrUser($this->tenant, ['hr.clock.manage']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/hr/clock/in')->assertSuccessful();
});

test('user WITHOUT hr.clock.manage gets 403 on clock in', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/hr/clock/in')->assertForbidden();
});

test('user WITH hr.clock.manage can clock out', function () {
    $user = hrUser($this->tenant, ['hr.clock.manage']);
    Sanctum::actingAs($user, ['*']);

    // Must have an open clock-in entry first
    TimeClockEntry::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $user->id,
        'clock_in' => now()->subHour(),
        'type' => 'regular',
    ]);

    $this->postJson('/api/v1/hr/clock/out')->assertSuccessful();
});

test('user WITHOUT hr.clock.manage gets 403 on clock out', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/hr/clock/out')->assertForbidden();
});

// ============================================================
// Advanced Clock
// ============================================================

test('user WITH hr.clock.view can access clock status', function () {
    $user = hrUser($this->tenant, ['hr.clock.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/advanced/clock/status')->assertOk();
});

test('user WITHOUT hr.clock.view gets 403 on clock status', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/advanced/clock/status')->assertForbidden();
});

test('user WITH hr.clock.view can access pending clock entries', function () {
    $user = hrUser($this->tenant, ['hr.clock.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/advanced/clock/pending')->assertOk();
});

test('user WITHOUT hr.clock.view gets 403 on pending clock entries', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/advanced/clock/pending')->assertForbidden();
});

test('user WITH hr.clock.manage can do advanced clock in', function () {
    $user = hrUser($this->tenant, ['hr.clock.manage']);
    Sanctum::actingAs($user, ['*']);

    // Portaria 671: GPS + selfie are now required — sending without them returns 422 (validation)
    // This test verifies the user is NOT blocked by permission (403), so 422 is acceptable
    $response = $this->postJson('/api/v1/hr/advanced/clock-in');
    $this->assertNotEquals(403, $response->status(), 'User with hr.clock.manage should not get 403');
});

test('user WITHOUT hr.clock.manage gets 403 on advanced clock in', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/hr/advanced/clock-in')->assertForbidden();
});

// ============================================================
// Leaves / Vacations
// ============================================================

test('user WITH hr.leave.view can list leaves', function () {
    $user = hrUser($this->tenant, ['hr.leave.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/leaves')->assertOk();
});

test('user WITHOUT hr.leave.view gets 403 on list leaves', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/leaves')->assertForbidden();
});

test('user WITH hr.leave.view can access vacation balances', function () {
    $user = hrUser($this->tenant, ['hr.leave.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/vacation-balances')->assertOk();
});

test('user WITHOUT hr.leave.view gets 403 on vacation balances', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/vacation-balances')->assertForbidden();
});

test('user WITH hr.leave.create can store leave request', function () {
    $user = hrUser($this->tenant, ['hr.leave.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/hr/leaves', [
        'type' => 'vacation',
        'start_date' => now()->addDays(30)->toDateString(),
        'end_date' => now()->addDays(40)->toDateString(),
        'reason' => 'Ferias regulamentares',
    ])->assertStatus(201);
});

test('user WITHOUT hr.leave.create gets 403 on store leave', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/hr/leaves', [
        'type' => 'vacation',
    ])->assertForbidden();
});

// ============================================================
// HR Schedules
// ============================================================

test('user WITH hr.schedule.view can list schedules', function () {
    $user = hrUser($this->tenant, ['hr.schedule.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/schedules')->assertOk();
});

test('user WITHOUT hr.schedule.view gets 403 on list schedules', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/schedules')->assertForbidden();
});

test('user WITH hr.schedule.view can access HR dashboard', function () {
    $user = hrUser($this->tenant, ['hr.schedule.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/dashboard')->assertOk();
});

test('user WITHOUT hr.schedule.view gets 403 on HR dashboard', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/dashboard')->assertForbidden();
});

test('user WITH hr.schedule.manage can store schedule', function () {
    $user = hrUser($this->tenant, ['hr.schedule.manage']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/hr/schedules', [
        'technician_id' => $user->id,
        'date' => now()->addDay()->toDateString(),
        'shift_type' => 'normal',
        'start_time' => '08:00',
        'end_time' => '17:00',
    ])->assertStatus(201);
});

test('user WITHOUT hr.schedule.manage gets 403 on store schedule', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/hr/schedules', [
        'technician_id' => $user->id,
    ])->assertForbidden();
});

// ============================================================
// Training
// ============================================================

test('user WITH hr.training.view can list trainings', function () {
    $user = hrUser($this->tenant, ['hr.training.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/trainings')->assertOk();
});

test('user WITHOUT hr.training.view gets 403 on list trainings', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/trainings')->assertForbidden();
});

test('user WITH hr.training.manage can store training', function () {
    $user = hrUser($this->tenant, ['hr.training.manage']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/hr/trainings', [
        'user_id' => $user->id,
        'title' => 'Treinamento NR35',
        'institution' => 'SENAI',
        'category' => 'safety',
        'hours' => 8,
        'completion_date' => now()->subDays(10)->toDateString(),
        'status' => 'completed',
    ])->assertStatus(201);
});

test('user WITHOUT hr.training.manage gets 403 on store training', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/hr/trainings', [
        'name' => 'Treinamento NR35',
    ])->assertForbidden();
});

// ============================================================
// Performance Reviews
// ============================================================

test('user WITH hr.performance.view can list reviews', function () {
    $user = hrUser($this->tenant, ['hr.performance.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/reviews')->assertOk();
});

test('user WITHOUT hr.performance.view gets 403 on list reviews', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/reviews')->assertForbidden();
});

// ============================================================
// Geofences
// ============================================================

test('user WITH hr.geofence.view can list geofences', function () {
    $user = hrUser($this->tenant, ['hr.geofence.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/geofences')->assertOk();
});

test('user WITHOUT hr.geofence.view gets 403 on geofences', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/geofences')->assertForbidden();
});

// ============================================================
// Adjustments
// ============================================================

test('user WITH hr.adjustment.view can list adjustments', function () {
    $user = hrUser($this->tenant, ['hr.adjustment.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/adjustments')->assertOk();
});

test('user WITHOUT hr.adjustment.view gets 403 on adjustments', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/adjustments')->assertForbidden();
});

test('user WITH hr.adjustment.create can store adjustment', function () {
    $user = hrUser($this->tenant, ['hr.adjustment.create']);
    Sanctum::actingAs($user, ['*']);

    // Create a time clock entry to reference
    $clockEntry = TimeClockEntry::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $user->id,
        'clock_in' => now()->subDay()->setTime(8, 0),
        'clock_out' => now()->subDay()->setTime(17, 0),
        'type' => 'regular',
    ]);

    $this->postJson('/api/v1/hr/adjustments', [
        'time_clock_entry_id' => $clockEntry->id,
        'adjusted_clock_in' => now()->subDay()->setTime(7, 50)->toDateTimeString(),
        'reason' => 'Esqueceu de bater o ponto no horario correto',
    ])->assertStatus(201);
});

test('user WITHOUT hr.adjustment.create gets 403 on store adjustment', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/hr/adjustments', [
        'user_id' => $user->id,
    ])->assertForbidden();
});

// ============================================================
// Documents
// ============================================================

test('user WITH hr.document.view can list documents', function () {
    $user = hrUser($this->tenant, ['hr.document.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/documents')->assertOk();
});

test('user WITHOUT hr.document.view gets 403 on documents', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/documents')->assertForbidden();
});

// ============================================================
// Holidays
// ============================================================

test('user WITH hr.holiday.view can list holidays', function () {
    $user = hrUser($this->tenant, ['hr.holiday.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/holidays')->assertOk();
});

test('user WITHOUT hr.holiday.view gets 403 on holidays', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/holidays')->assertForbidden();
});

// ============================================================
// Journey Rules
// ============================================================

test('user WITH hr.journey.view can list journey rules', function () {
    $user = hrUser($this->tenant, ['hr.journey.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/journey-rules')->assertOk();
});

test('user WITHOUT hr.journey.view gets 403 on journey rules', function () {
    $user = hrUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/hr/journey-rules')->assertForbidden();
});
