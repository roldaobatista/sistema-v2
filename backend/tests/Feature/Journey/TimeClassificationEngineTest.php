<?php

namespace Tests\Feature\Journey;

use App\Enums\TimeClassificationType;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyRule;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Journey\TimeClassificationEngine;
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

    $this->policy = JourneyRule::factory()->default()->create([
        'tenant_id' => $this->tenant->id,
        'daily_hours_limit' => 480,
        'break_minutes' => 60,
        'displacement_counts_as_work' => false,
        'saturday_is_overtime' => false,
        'sunday_is_overtime' => true,
    ]);

    $this->engine = app(TimeClassificationEngine::class);
    $this->date = Carbon::parse('2026-04-09'); // Thursday
});

it('classifies a simple day: clock in 08h, break 12-13h, clock out 17h', function () {
    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
        'break_start' => '2026-04-09 12:00:00',
        'break_end' => '2026-04-09 13:00:00',
    ]);

    $journeyDay = $this->engine->classifyDay($this->user, $this->date, $this->policy);

    expect($journeyDay)->not->toBeNull();
    expect($journeyDay->date->format('Y-m-d'))->toBe('2026-04-09');
    expect($journeyDay->blocks)->not->toBeEmpty();
    expect($journeyDay->total_minutes_break)->toBeGreaterThanOrEqual(60);
    expect($journeyDay->total_minutes_worked)->toBeGreaterThanOrEqual(420); // ~7-8h work
});

it('classifies day with OS: displacement + service execution', function () {
    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
    ]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_to' => $this->user->id,
        'started_at' => '2026-04-09 09:00:00',
        'completed_at' => '2026-04-09 11:00:00',
        'status' => 'completed',
    ]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_to' => $this->user->id,
        'started_at' => '2026-04-09 11:45:00',
        'completed_at' => '2026-04-09 16:00:00',
        'status' => 'completed',
    ]);

    $journeyDay = $this->engine->classifyDay($this->user, $this->date, $this->policy);

    $classifications = $journeyDay->blocks->pluck('classification')->map(fn ($c) => $c->value)->toArray();

    // Should have displacement and service execution blocks
    expect($classifications)->toContain(TimeClassificationType::DESLOCAMENTO_CLIENTE->value);
    expect($classifications)->toContain(TimeClassificationType::EXECUCAO_SERVICO->value);
});

it('classifies overtime when exceeding daily limit', function () {
    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 19:00:00', // 11h total, 8h limit
    ]);

    $journeyDay = $this->engine->classifyDay($this->user, $this->date, $this->policy);

    $overtimeBlocks = $journeyDay->blocks->filter(
        fn ($b) => $b->classification === TimeClassificationType::HORA_EXTRA
    );

    expect($overtimeBlocks)->not->toBeEmpty();
    expect($journeyDay->total_minutes_overtime)->toBeGreaterThan(0);
});

it('classifies displacement as work when policy allows', function () {
    $workPolicy = JourneyRule::factory()->displacementAsWork()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
    ]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_to' => $this->user->id,
        'started_at' => '2026-04-09 09:00:00',
        'completed_at' => '2026-04-09 16:00:00',
        'status' => 'completed',
    ]);

    $journeyDay = $this->engine->classifyDay($this->user, $this->date, $workPolicy);

    $displacementBlocks = $journeyDay->blocks->filter(
        fn ($b) => $b->classification === TimeClassificationType::DESLOCAMENTO_CLIENTE
    );

    // When displacement counts as work, there should be NO displacement blocks
    expect($displacementBlocks)->toBeEmpty();
});

it('creates journey day with correct regime from policy', function () {
    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
    ]);

    $journeyDay = $this->engine->classifyDay($this->user, $this->date, $this->policy);

    expect($journeyDay->regime_type)->toBe($this->policy->regime_type);
    expect($journeyDay->tenant_id)->toBe($this->tenant->id);
    expect($journeyDay->user_id)->toBe($this->user->id);
});

it('is idempotent: reclassifying same day does not duplicate blocks', function () {
    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
    ]);

    $first = $this->engine->classifyDay($this->user, $this->date, $this->policy);
    $firstBlockCount = $first->blocks->count();

    $second = $this->engine->classifyDay($this->user, $this->date, $this->policy);

    expect($second->id)->toBe($first->id);
    expect($second->blocks->count())->toBe($firstBlockCount);
});

it('returns empty journey day when no events', function () {
    $journeyDay = $this->engine->classifyDay($this->user, $this->date, $this->policy);

    expect($journeyDay)->not->toBeNull();
    expect($journeyDay->blocks)->toBeEmpty();
    expect($journeyDay->total_minutes_worked)->toBe(0);
});

it('classifies break correctly from time clock entry', function () {
    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
        'break_start' => '2026-04-09 12:00:00',
        'break_end' => '2026-04-09 13:00:00',
    ]);

    $journeyDay = $this->engine->classifyDay($this->user, $this->date, $this->policy);

    $breakBlocks = $journeyDay->blocks->filter(
        fn ($b) => $b->classification === TimeClassificationType::INTERVALO
    );

    expect($breakBlocks)->not->toBeEmpty();
    expect($journeyDay->total_minutes_break)->toBeGreaterThanOrEqual(60);
});

it('does not classify data from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
    ]);

    TimeClockEntry::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
        'type' => 'regular',
    ]);

    // Classify for current user (no entries)
    $journeyDay = $this->engine->classifyDay($this->user, $this->date, $this->policy);

    expect($journeyDay->blocks)->toBeEmpty();
    expect($journeyDay->total_minutes_worked)->toBe(0);
});

it('classifies displacement between two OS correctly', function () {
    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
    ]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_to' => $this->user->id,
        'started_at' => '2026-04-09 09:00:00',
        'completed_at' => '2026-04-09 11:00:00',
        'status' => 'completed',
    ]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_to' => $this->user->id,
        'started_at' => '2026-04-09 12:00:00',
        'completed_at' => '2026-04-09 16:00:00',
        'status' => 'completed',
    ]);

    $journeyDay = $this->engine->classifyDay($this->user, $this->date, $this->policy);

    $betweenBlocks = $journeyDay->blocks->filter(
        fn ($b) => $b->classification === TimeClassificationType::DESLOCAMENTO_ENTRE
    );

    expect($betweenBlocks)->not->toBeEmpty();
});
