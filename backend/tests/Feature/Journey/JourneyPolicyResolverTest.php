<?php

namespace Tests\Feature\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyRule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Journey\JourneyPolicyResolver;
use Carbon\Carbon;
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
    $this->resolver = app(JourneyPolicyResolver::class);
});

it('returns default policy when one exists', function () {
    $policy = JourneyRule::factory()->default()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $resolved = $this->resolver->resolve($this->user);

    expect($resolved->id)->toBe($policy->id);
    expect($resolved->is_default)->toBeTrue();
});

it('creates default CLT policy when none exists', function () {
    expect(JourneyRule::count())->toBe(0);

    $resolved = $this->resolver->resolve($this->user);

    expect($resolved)->toBeInstanceOf(JourneyRule::class);
    expect($resolved->name)->toBe('CLT Padrão');
    expect($resolved->regime_type)->toBe('clt_mensal');
    expect($resolved->daily_hours_limit)->toBe(480);
    expect($resolved->is_default)->toBeTrue();
    expect(JourneyRule::withoutGlobalScope('tenant')->count())->toBe(1);
});

it('does not return inactive default policy', function () {
    JourneyRule::factory()->default()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => false,
    ]);

    $resolved = $this->resolver->resolve($this->user);

    // Should create a new default
    expect($resolved->name)->toBe('CLT Padrão');
    expect($resolved->is_active)->toBeTrue();
});

it('does not return policy from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    JourneyRule::factory()->default()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    $resolved = $this->resolver->resolve($this->user);

    expect($resolved->tenant_id)->toBe($this->tenant->id);
    expect($resolved->name)->toBe('CLT Padrão');
});

it('journey policy correctly identifies overtime days', function () {
    $policy = JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'saturday_is_overtime' => true,
        'sunday_is_overtime' => true,
    ]);

    $saturday = Carbon::parse('2026-04-11'); // Saturday
    $sunday = Carbon::parse('2026-04-12');   // Sunday
    $monday = Carbon::parse('2026-04-13');   // Monday

    expect($policy->isOvertimeDay($saturday))->toBeTrue();
    expect($policy->isOvertimeDay($sunday))->toBeTrue();
    expect($policy->isOvertimeDay($monday))->toBeFalse();
});

it('journey policy saturday not overtime when configured', function () {
    $policy = JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'saturday_is_overtime' => false,
        'sunday_is_overtime' => true,
    ]);

    $saturday = Carbon::parse('2026-04-11');

    expect($policy->isOvertimeDay($saturday))->toBeFalse();
});
