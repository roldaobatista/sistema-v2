<?php

namespace Tests\Feature\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyBlock;
use App\Models\JourneyEntry;
use App\Models\JourneyRule;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->withoutMiddleware([
        EnsureTenantScope::class,
        CheckPermission::class,
    ]);
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    app()->instance('current_tenant_id', $this->tenant->id);
    Sanctum::actingAs($this->user, ['*']);

    JourneyRule::factory()->default()->create([
        'tenant_id' => $this->tenant->id,
    ]);
});

// === 1. Sucesso CRUD ===

it('can list journey days with pagination', function () {
    foreach (['2026-04-07', '2026-04-08', '2026-04-09'] as $date) {
        JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => $date,
        ]);
    }

    $response = $this->getJson('/api/v1/journey/days');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'user_id', 'reference_date', 'regime_type', 'total_minutes_worked',
                'operational_approval_status', 'hr_approval_status', 'is_closed']],
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
});

it('can show journey day with blocks', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    JourneyBlock::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $journeyDay->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->getJson("/api/v1/journey/days/{$journeyDay->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $journeyDay->id)
        ->assertJsonCount(2, 'data.blocks')
        ->assertJsonStructure([
            'data' => [
                'id', 'user_id', 'reference_date', 'blocks' => [
                    ['id', 'classification', 'classification_label', 'started_at', 'ended_at', 'duration_minutes', 'source'],
                ],
            ],
        ]);
});

it('can reclassify a journey day', function () {
    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
    ]);

    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);

    $response = $this->postJson("/api/v1/journey/days/{$journeyDay->id}/reclassify");

    $response->assertOk()
        ->assertJsonPath('data.id', $journeyDay->id)
        ->assertJsonStructure(['data' => ['id', 'blocks']]);
});

// === 2. Filtros ===

it('can filter journey days by date range', function () {
    JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-01',
    ]);

    JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-15',
    ]);

    $response = $this->getJson('/api/v1/journey/days?date_from=2026-04-10&date_to=2026-04-20');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can filter journey days by user_id', function () {
    $otherUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-01',
    ]);

    JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $otherUser->id,
        'date' => '2026-04-02',
    ]);

    $response = $this->getJson("/api/v1/journey/days?user_id={$otherUser->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $otherUser->id);
});

// === 3. Cross-tenant 404 ===

it('cannot access journey day from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
    ]);

    $otherDay = JourneyEntry::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
        'date' => '2026-04-09',
        'regime_type' => 'clt_mensal',
    ]);

    $this->getJson("/api/v1/journey/days/{$otherDay->id}")
        ->assertNotFound();
});

it('lists only journey days from current tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
    ]);

    JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    JourneyEntry::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
        'date' => '2026-04-02',
        'regime_type' => 'clt_mensal',
    ]);

    $response = $this->getJson('/api/v1/journey/days');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

// === 4. Permissão 403 ===

it('denies access without hr.clock.view permission', function () {
    // Fresh user without any permissions, no Gate bypass, no middleware bypass
    $freshUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    Sanctum::actingAs($freshUser, ['*']);

    $this->withMiddleware([
        CheckPermission::class,
    ]);

    // Remove Gate bypass for this test
    app(\Illuminate\Contracts\Auth\Access\Gate::class)->before(fn () => null);

    $this->getJson('/api/v1/journey/days')
        ->assertStatus(403);
});

// === 5. Edge cases ===

it('returns empty list when no journey days', function () {
    $response = $this->getJson('/api/v1/journey/days');

    $response->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonStructure(['data', 'meta']);
});

it('journey day resource includes computed fields', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'operational_approval_status' => 'pending',
        'hr_approval_status' => 'pending',
    ]);

    $response = $this->getJson("/api/v1/journey/days/{$journeyDay->id}");

    $response->assertOk()
        ->assertJsonPath('data.is_pending_approval', true)
        ->assertJsonPath('data.is_fully_approved', false);
});
