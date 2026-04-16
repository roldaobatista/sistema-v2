<?php

namespace Tests\Feature\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyRule;
use App\Models\Tenant;
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
});

// === 1. Sucesso CRUD ===

it('can list journey policies', function () {
    JourneyRule::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->getJson('/api/v1/journey/policies');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'name', 'regime_type', 'daily_hours_limit', 'is_default', 'is_active']],
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
});

it('can show a journey policy', function () {
    $policy = JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->getJson("/api/v1/journey/policies/{$policy->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $policy->id)
        ->assertJsonPath('data.name', $policy->name)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'regime_type', 'daily_hours_limit', 'weekly_hours_limit',
                'break_minutes', 'displacement_counts_as_work', 'is_default', 'is_active'],
        ]);
});

it('can create a journey policy', function () {
    $payload = [
        'name' => 'CLT Padrão Teste',
        'regime_type' => 'clt_mensal',
        'daily_hours_limit' => 480,
        'weekly_hours_limit' => 2640,
        'break_minutes' => 60,
        'displacement_counts_as_work' => false,
        'wait_time_counts_as_work' => true,
        'travel_meal_counts_as_break' => true,
        'auto_suggest_clock_on_displacement' => true,
        'pre_assigned_break' => false,
        'overnight_min_hours' => 11,
        'oncall_multiplier_percent' => 33,
        'saturday_is_overtime' => false,
        'sunday_is_overtime' => true,
        'is_default' => false,
        'is_active' => true,
    ];

    $response = $this->postJson('/api/v1/journey/policies', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'CLT Padrão Teste')
        ->assertJsonPath('data.regime_type', 'clt_mensal');

    $this->assertDatabaseHas('journey_rules', [
        'name' => 'CLT Padrão Teste',
        'tenant_id' => $this->tenant->id,
    ]);
});

it('can update a journey policy', function () {
    $policy = JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Original',
    ]);

    $response = $this->putJson("/api/v1/journey/policies/{$policy->id}", [
        'name' => 'Updated',
        'daily_hours_limit' => 360,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated')
        ->assertJsonPath('data.daily_hours_limit', 360);
});

it('can delete a journey policy', function () {
    $policy = JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->deleteJson("/api/v1/journey/policies/{$policy->id}")
        ->assertNoContent();

    expect(JourneyRule::find($policy->id))->toBeNull();
    expect(JourneyRule::withTrashed()->find($policy->id))->not->toBeNull();
});

// === 2. Validação 422 ===

it('fails validation when required fields are missing', function () {
    $response = $this->postJson('/api/v1/journey/policies', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'regime_type', 'daily_hours_limit']);
});

it('fails validation with invalid regime_type', function () {
    $response = $this->postJson('/api/v1/journey/policies', [
        'name' => 'Test',
        'regime_type' => 'invalid_regime',
        'daily_hours_limit' => 480,
        'weekly_hours_limit' => 2640,
        'break_minutes' => 60,
        'displacement_counts_as_work' => false,
        'wait_time_counts_as_work' => true,
        'travel_meal_counts_as_break' => true,
        'auto_suggest_clock_on_displacement' => true,
        'pre_assigned_break' => false,
        'overnight_min_hours' => 11,
        'oncall_multiplier_percent' => 33,
        'saturday_is_overtime' => false,
        'sunday_is_overtime' => true,
        'is_default' => false,
        'is_active' => true,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['regime_type']);
});

// === 3. Cross-tenant 404 ===

it('cannot access policy from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherPolicy = JourneyRule::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other Tenant Policy',
        'regime_type' => 'clt_mensal',
        'is_active' => true,
    ]);

    $this->getJson("/api/v1/journey/policies/{$otherPolicy->id}")
        ->assertNotFound();
});

it('lists only policies from current tenant', function () {
    $otherTenant = Tenant::factory()->create();

    JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    JourneyRule::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other',
        'regime_type' => 'clt_mensal',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/journey/policies');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

// === 4. Permissão 403 ===

it('denies access without permission to list policies', function () {
    $freshUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    Sanctum::actingAs($freshUser, ['*']);

    $this->withMiddleware([
        CheckPermission::class,
    ]);

    app(\Illuminate\Contracts\Auth\Access\Gate::class)->before(fn () => null);

    $this->getJson('/api/v1/journey/policies')
        ->assertStatus(403);
});

// === 5. Edge cases ===

it('setting new default unsets previous default', function () {
    $oldDefault = JourneyRule::factory()->default()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->postJson('/api/v1/journey/policies', [
        'name' => 'New Default',
        'regime_type' => 'clt_mensal',
        'daily_hours_limit' => 480,
        'weekly_hours_limit' => 2640,
        'break_minutes' => 60,
        'displacement_counts_as_work' => false,
        'wait_time_counts_as_work' => true,
        'travel_meal_counts_as_break' => true,
        'auto_suggest_clock_on_displacement' => true,
        'pre_assigned_break' => false,
        'overnight_min_hours' => 11,
        'oncall_multiplier_percent' => 33,
        'saturday_is_overtime' => false,
        'sunday_is_overtime' => true,
        'is_default' => true,
        'is_active' => true,
    ])->assertCreated();

    $oldDefault->refresh();
    expect($oldDefault->is_default)->toBeFalse();

    expect(JourneyRule::where('is_default', true)->count())->toBe(1);
});

it('tenant_id is assigned automatically from authenticated user', function () {
    $this->postJson('/api/v1/journey/policies', [
        'name' => 'Auto Tenant',
        'regime_type' => 'clt_mensal',
        'daily_hours_limit' => 480,
        'weekly_hours_limit' => 2640,
        'break_minutes' => 60,
        'displacement_counts_as_work' => false,
        'wait_time_counts_as_work' => true,
        'travel_meal_counts_as_break' => true,
        'auto_suggest_clock_on_displacement' => true,
        'pre_assigned_break' => false,
        'overnight_min_hours' => 11,
        'oncall_multiplier_percent' => 33,
        'saturday_is_overtime' => false,
        'sunday_is_overtime' => true,
        'is_default' => false,
        'is_active' => true,
    ])->assertCreated();

    $this->assertDatabaseHas('journey_rules', [
        'name' => 'Auto Tenant',
        'tenant_id' => $this->tenant->id,
    ]);
});
