<?php

namespace Tests\Feature\Journey;

use App\Enums\TimeClassificationType;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyBlock;
use App\Models\JourneyEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ESocial\JourneyESocialGenerator;
use App\Services\Journey\PayrollIntegrationService;
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

// === ESocial Generator ===

it('generates S-1200 events from closed journey days', function () {
    // Create 3 closed journey days for April
    foreach (['2026-04-07', '2026-04-08', '2026-04-09'] as $date) {
        $day = JourneyEntry::factory()->approved()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'total_minutes_worked' => 480,
            'total_minutes_overtime' => 60,
        ]);

        JourneyBlock::factory()->create([
            'tenant_id' => $this->tenant->id,
            'journey_entry_id' => $day->id,
            'user_id' => $this->user->id,
            'classification' => TimeClassificationType::JORNADA_NORMAL->value,
            'duration_minutes' => 480,
            'started_at' => "{$date} 08:00:00",
            'ended_at' => "{$date} 16:00:00",
        ]);
    }

    $generator = app(JourneyESocialGenerator::class);
    $events = $generator->generateS1200ForMonth($this->tenant->id, '2026-04');

    expect($events)->toHaveCount(1); // 1 event per user
    expect($events->first()->event_type)->toBe('S-1200');
    expect($events->first()->status)->toBe('pending');
    expect($events->first()->xml_content)->toContain('evtRemun');
    expect($events->first()->xml_content)->toContain('HE50');
});

it('generates S-2230 for absence days', function () {
    $day = JourneyEntry::factory()->approved()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-10',
    ]);

    JourneyBlock::factory()->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $day->id,
        'user_id' => $this->user->id,
        'classification' => TimeClassificationType::ATESTADO->value,
        'duration_minutes' => 480,
        'started_at' => '2026-04-10 08:00:00',
        'ended_at' => '2026-04-10 16:00:00',
    ]);

    $generator = app(JourneyESocialGenerator::class);
    $events = $generator->generateS2230ForAbsences($this->tenant->id, '2026-04');

    expect($events)->toHaveCount(1);
    expect($events->first()->event_type)->toBe('S-2230');
    expect($events->first()->xml_content)->toContain('evtAfastTemp');
});

it('does not generate S-1200 for unclosed days', function () {
    JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-07',
        'is_closed' => false,
    ]);

    $generator = app(JourneyESocialGenerator::class);
    $events = $generator->generateS1200ForMonth($this->tenant->id, '2026-04');

    expect($events)->toBeEmpty();
});

it('does not generate events for another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
    ]);

    JourneyEntry::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
        'date' => '2026-04-07',
        'regime_type' => 'clt_mensal',
        'is_closed' => true,
        'operational_approval_status' => 'approved',
        'hr_approval_status' => 'approved',
    ]);

    $generator = app(JourneyESocialGenerator::class);
    $events = $generator->generateS1200ForMonth($this->tenant->id, '2026-04');

    expect($events)->toBeEmpty();
});

// === Payroll Integration ===

it('exports month summary for payroll', function () {
    foreach (['2026-04-07', '2026-04-08', '2026-04-09'] as $date) {
        JourneyEntry::factory()->approved()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'total_minutes_worked' => 480,
            'total_minutes_overtime' => 60,
        ]);
    }

    $service = app(PayrollIntegrationService::class);
    $summary = $service->exportMonthSummary($this->tenant->id, '2026-04');

    expect($summary)->toHaveCount(1);
    expect($summary->first()['user_id'])->toBe($this->user->id);
    expect($summary->first()['working_days'])->toBe(3);
    expect($summary->first()['total_worked_hours'])->toBe(24.0);  // 3 * 480min / 60
    expect($summary->first()['total_overtime_hours'])->toBe(3.0);  // 3 * 60min / 60
    expect($summary->first()['all_days_approved'])->toBeTrue();
});

it('detects blocking unclosed days', function () {
    JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-07',
        'is_closed' => false,
        'operational_approval_status' => 'pending',
    ]);

    JourneyEntry::factory()->approved()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-08',
    ]);

    $service = app(PayrollIntegrationService::class);
    $blocking = $service->getBlockingDays($this->tenant->id, '2026-04');

    expect($blocking)->toHaveCount(1);
    expect($blocking->first()['date'])->toBe('2026-04-07');
});

it('returns empty blocking when all days are closed', function () {
    JourneyEntry::factory()->approved()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-07',
    ]);

    $service = app(PayrollIntegrationService::class);
    $blocking = $service->getBlockingDays($this->tenant->id, '2026-04');

    expect($blocking)->toBeEmpty();
});
