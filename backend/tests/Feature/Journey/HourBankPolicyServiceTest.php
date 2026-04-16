<?php

namespace Tests\Feature\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyRule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Journey\HourBankPolicyService;
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
    $this->service = app(HourBankPolicyService::class);
});

it('resolves existing policy for tenant', function () {
    $policy = JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $resolved = $this->service->resolvePolicy($this->user);

    expect($resolved->id)->toBe($policy->id);
});

it('creates default policy when none exists', function () {
    $resolved = $this->service->resolvePolicy($this->user);

    expect($resolved)->toBeInstanceOf(JourneyRule::class);
    expect($resolved->name)->toBe('CLT Padrão');
    expect($resolved->compensation_period_days)->toBe(30);
});

it('returns zero balance for user without transactions', function () {
    $balance = $this->service->getBalance($this->user);

    expect($balance)->toBe(0);
});

it('adds credit entry and updates balance', function () {
    JourneyRule::factory()->create(['tenant_id' => $this->tenant->id]);

    $transaction = $this->service->addEntry(
        $this->user,
        120, // 2h
        'credit',
        Carbon::parse('2026-04-09'),
        notes: 'Hora extra dia 09/04',
    );

    expect($transaction->hours)->toBe('2.00');
    expect($transaction->balance_before)->toBe('0.00');
    expect($transaction->balance_after)->toBe('2.00');

    $balance = $this->service->getBalance($this->user);
    expect($balance)->toBe(120);
});

it('adds debit entry and updates balance', function () {
    JourneyRule::factory()->create(['tenant_id' => $this->tenant->id]);

    // Add credit first
    $this->service->addEntry($this->user, 240, 'credit', Carbon::parse('2026-04-08'));

    // Then debit
    $transaction = $this->service->addEntry(
        $this->user,
        -60, // -1h
        'debit',
        Carbon::parse('2026-04-09'),
        notes: 'Compensação folga',
    );

    expect($transaction->balance_after)->toBe('3.00'); // 4h - 1h = 3h
    expect($this->service->getBalance($this->user))->toBe(180); // 3h in minutes
});

it('blocks negative balance exceeding policy limit', function () {
    JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'max_negative_balance_minutes' => 120, // max -2h
        'block_on_negative_exceeded' => true,
    ]);

    // Try to go -3h (exceeds -2h limit)
    $transaction = $this->service->addEntry(
        $this->user,
        -180,
        'debit',
        Carbon::parse('2026-04-09'),
    );

    // Should be clamped to 0 (can't go below what balance allows)
    expect((float) $transaction->balance_after)->toBe(0.00);
});

it('detects exceeded positive balance', function () {
    $policy = JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'max_positive_balance_minutes' => 600, // 10h max
    ]);

    expect($policy->isBalanceExceeded(500))->toBeFalse();
    expect($policy->isBalanceExceeded(601))->toBeTrue();
    expect($policy->isBalanceExceeded(-100))->toBeFalse();
});

it('calculates expiration date from policy', function () {
    $policy = JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'compensation_period_days' => 180,
    ]);

    $expDate = $policy->getExpirationDate(Carbon::parse('2026-01-01'));

    expect($expDate->format('Y-m-d'))->toBe('2026-06-30');
});

it('calculates overtime value with multipliers', function () {
    $policy = JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'overtime_50_multiplier' => 1.50,
        'overtime_100_multiplier' => 2.00,
    ]);

    // 120 min = 2h at 50% = 2 * 1.5 = 3.0
    expect($policy->calculateOvertimeValue(120, '50'))->toBe(3.0);

    // 60 min = 1h at 100% = 1 * 2.0 = 2.0
    expect($policy->calculateOvertimeValue(60, '100'))->toBe(2.0);
});

it('generates monthly snapshot correctly', function () {
    JourneyRule::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->service->addEntry($this->user, 120, 'credit', Carbon::parse('2026-04-05'));
    $this->service->addEntry($this->user, 60, 'credit', Carbon::parse('2026-04-10'));
    $this->service->addEntry($this->user, -30, 'debit', Carbon::parse('2026-04-15'));

    $snapshot = $this->service->getMonthlySnapshot($this->user, '2026-04');

    expect($snapshot['user_id'])->toBe($this->user->id);
    expect($snapshot['year_month'])->toBe('2026-04');
    expect($snapshot['credits_hours'])->toBe(3.0);   // 2h + 1h
    expect($snapshot['debits_hours'])->toBe(0.5);     // 30min
    expect($snapshot['net_hours'])->toBe(2.5);         // 3h - 0.5h
    expect($snapshot['transactions_count'])->toBe(3);
});

it('does not resolve policy from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    JourneyRule::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other',
        'is_active' => true,
    ]);

    $resolved = $this->service->resolvePolicy($this->user);

    expect($resolved->tenant_id)->toBe($this->tenant->id);
    expect($resolved->name)->toBe('CLT Padrão'); // Created default
});

it('handles multiple policies per tenant picking first active', function () {
    JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Policy A',
        'is_active' => true,
    ]);

    JourneyRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Policy B',
        'is_active' => true,
    ]);

    $resolved = $this->service->resolvePolicy($this->user);
    expect($resolved->name)->toBe('Policy A');
});
