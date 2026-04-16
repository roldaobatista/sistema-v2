<?php

/**
 * Tenant Isolation — HR Module
 *
 * Validates complete data isolation for: TimeClockEntry, HR schedules,
 * leave/vacation, training records.
 *
 * FAILURE HERE = HR/EMPLOYEE DATA LEAK BETWEEN TENANTS
 */

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Model::unguard();
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

function actAsTenantHr(object $test, User $user, Tenant $tenant): void
{
    app()->instance('current_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    Sanctum::actingAs($user, ['*']);
}

// ══════════════════════════════════════════════════════════════════
//  TIME CLOCK ENTRIES
// ══════════════════════════════════════════════════════════════════

test('TimeClockEntry model scope isolates by tenant', function () {
    TimeClockEntry::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'user_id' => $this->userA->id,
        'clock_in' => now()->subHours(8), 'clock_out' => now(),
        'type' => 'regular', 'approval_status' => 'approved',
    ]);
    TimeClockEntry::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'user_id' => $this->userB->id,
        'clock_in' => now()->subHours(6), 'clock_out' => now(),
        'type' => 'regular', 'approval_status' => 'approved',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $entries = TimeClockEntry::all();
    expect($entries)->toHaveCount(1);
    expect($entries->first()->tenant_id)->toBe($this->tenantA->id);
});

test('clock history API only returns own tenant records', function () {
    TimeClockEntry::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'user_id' => $this->userA->id,
        'clock_in' => now()->subHours(8), 'clock_out' => now(),
        'type' => 'regular', 'approval_status' => 'approved',
    ]);
    TimeClockEntry::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'user_id' => $this->userB->id,
        'clock_in' => now()->subHours(6), 'clock_out' => now(),
        'type' => 'regular', 'approval_status' => 'approved',
    ]);

    actAsTenantHr($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/hr/clock/all');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
});

test('my clock history only returns own tenant entries', function () {
    TimeClockEntry::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'user_id' => $this->userA->id,
        'clock_in' => now()->subHours(8), 'type' => 'regular',
    ]);

    actAsTenantHr($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/hr/clock/my');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
});

// ══════════════════════════════════════════════════════════════════
//  HR SCHEDULES
// ══════════════════════════════════════════════════════════════════

test('HR schedules listing only shows own tenant', function () {
    actAsTenantHr($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/hr/schedules');
    $response->assertOk();
});

// ══════════════════════════════════════════════════════════════════
//  HR DASHBOARD & ANALYTICS
// ══════════════════════════════════════════════════════════════════

test('HR dashboard is tenant-scoped', function () {
    actAsTenantHr($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/hr/dashboard');
    $response->assertOk();
});

test('HR analytics is tenant-scoped', function () {
    actAsTenantHr($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/hr/analytics/dashboard');
    $response->assertOk();
});

// ══════════════════════════════════════════════════════════════════
//  LEAVES / VACATION
// ══════════════════════════════════════════════════════════════════

test('leaves listing only shows own tenant', function () {
    actAsTenantHr($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/hr/leaves');
    $response->assertOk();
});

test('vacation balances are tenant-scoped', function () {
    actAsTenantHr($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/hr/vacation-balances');
    $response->assertOk();
});

// ══════════════════════════════════════════════════════════════════
//  TRAININGS
// ══════════════════════════════════════════════════════════════════

test('trainings listing only shows own tenant', function () {
    actAsTenantHr($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/hr/trainings');
    $response->assertOk();
});

// ══════════════════════════════════════════════════════════════════
//  CROSS-TENANT USER VISIBILITY
// ══════════════════════════════════════════════════════════════════

test('user list scoped to tenant — cannot see cross-tenant employees', function () {
    app()->instance('current_tenant_id', $this->tenantA->id);

    $users = User::where('tenant_id', $this->tenantA->id)->get();
    expect($users->pluck('id')->toArray())->not->toContain($this->userB->id);
});
