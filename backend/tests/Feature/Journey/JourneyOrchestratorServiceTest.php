<?php

namespace Tests\Feature\Journey;

use App\Enums\TimeClassificationType;
use App\Events\JourneyDayUpdated;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyEntry;
use App\Models\JourneyRule;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Journey\JourneyOrchestratorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
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

    $this->orchestrator = app(JourneyOrchestratorService::class);
});

it('processes time clock event and creates journey day', function () {
    $entry = TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
    ]);

    Event::fake([JourneyDayUpdated::class]);

    $journeyDay = $this->orchestrator->processTimeClockEvent($entry);

    expect($journeyDay)->not->toBeNull();
    expect($journeyDay->user_id)->toBe($this->user->id);
    expect($journeyDay->date->format('Y-m-d'))->toBe('2026-04-09');
    expect($journeyDay->blocks)->not->toBeEmpty();

    Event::assertDispatched(JourneyDayUpdated::class, function ($e) use ($journeyDay) {
        return $e->journeyDay->id === $journeyDay->id && $e->trigger === 'time_clock';
    });
});

it('processes work order event and creates journey day', function () {
    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
    ]);

    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_to' => $this->user->id,
        'started_at' => '2026-04-09 09:00:00',
        'completed_at' => '2026-04-09 16:00:00',
        'status' => 'completed',
    ]);

    Event::fake([JourneyDayUpdated::class]);

    $journeyDay = $this->orchestrator->processWorkOrderEvent($workOrder);

    expect($journeyDay)->not->toBeNull();
    expect($journeyDay->blocks)->not->toBeEmpty();

    $serviceBlocks = $journeyDay->blocks->filter(
        fn ($b) => $b->classification === TimeClassificationType::EXECUCAO_SERVICO
    );
    expect($serviceBlocks)->not->toBeEmpty();

    Event::assertDispatched(JourneyDayUpdated::class);
});

it('returns null for work order without assigned_to', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_to' => null,
        'started_at' => '2026-04-09 09:00:00',
        'status' => 'pending',
    ]);

    $result = $this->orchestrator->processWorkOrderEvent($workOrder);

    expect($result)->toBeNull();
});

it('full flow: clock in → OS check-in → OS checkout → clock out → journey day consolidated', function () {
    $entry = TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
        'break_start' => '2026-04-09 12:00:00',
        'break_end' => '2026-04-09 13:00:00',
    ]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_to' => $this->user->id,
        'started_at' => '2026-04-09 09:00:00',
        'completed_at' => '2026-04-09 11:30:00',
        'status' => 'completed',
    ]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_to' => $this->user->id,
        'started_at' => '2026-04-09 13:30:00',
        'completed_at' => '2026-04-09 16:30:00',
        'status' => 'completed',
    ]);

    Event::fake([JourneyDayUpdated::class]);

    $journeyDay = $this->orchestrator->processTimeClockEvent($entry);

    expect($journeyDay)->not->toBeNull();
    expect($journeyDay->blocks->count())->toBeGreaterThanOrEqual(4);
    expect($journeyDay->total_minutes_worked)->toBeGreaterThan(0);
    expect($journeyDay->total_minutes_break)->toBeGreaterThanOrEqual(60);

    // Should have displacement, execution, and break blocks
    $types = $journeyDay->blocks->pluck('classification')->map(fn ($c) => $c->value)->unique()->toArray();
    expect($types)->toContain(TimeClassificationType::EXECUCAO_SERVICO->value);
    expect($types)->toContain(TimeClassificationType::INTERVALO->value);
});

it('is idempotent: reprocessing same day does not duplicate', function () {
    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
    ]);

    Event::fake([JourneyDayUpdated::class]);

    $date = Carbon::parse('2026-04-09');
    $first = $this->orchestrator->processDay($this->user, $date);
    $second = $this->orchestrator->processDay($this->user, $date);

    expect($second->id)->toBe($first->id);
    expect($second->blocks->count())->toBe($first->blocks->count());

    // Only 1 JourneyDay should exist
    expect(JourneyEntry::where('user_id', $this->user->id)->count())->toBe(1);
});

it('respects tenant isolation in orchestration', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
    ]);

    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
    ]);

    // Other tenant entry — should NOT appear
    TimeClockEntry::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
        'type' => 'regular',
    ]);

    Event::fake([JourneyDayUpdated::class]);

    $journeyDay = $this->orchestrator->processDay($this->user, Carbon::parse('2026-04-09'));

    expect($journeyDay->tenant_id)->toBe($this->tenant->id);

    // Other tenant should not have a journey day from this process
    $otherJourneyDays = JourneyEntry::withoutGlobalScope('tenant')
        ->where('tenant_id', $otherTenant->id)
        ->count();
    expect($otherJourneyDays)->toBe(0);
});

it('reprocessDay works correctly', function () {
    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => '2026-04-09 17:00:00',
    ]);

    Event::fake([JourneyDayUpdated::class]);

    $journeyDay = $this->orchestrator->reprocessDay($this->user->id, Carbon::parse('2026-04-09'));

    expect($journeyDay)->not->toBeNull();
    expect($journeyDay->user_id)->toBe($this->user->id);

    Event::assertDispatched(JourneyDayUpdated::class, function ($e) {
        return $e->trigger === 'reprocess';
    });
});

it('reprocessDay returns null for invalid user', function () {
    $result = $this->orchestrator->reprocessDay(99999, Carbon::parse('2026-04-09'));

    expect($result)->toBeNull();
});
