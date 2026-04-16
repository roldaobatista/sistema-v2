<?php

namespace Tests\Feature\Journey;

use App\Enums\TimeClassificationType;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyBlock;
use App\Models\JourneyEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
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

it('can create a journey day', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);

    expect($journeyDay)->toBeInstanceOf(JourneyEntry::class);
    expect($journeyDay->tenant_id)->toBe($this->tenant->id);
    expect($journeyDay->user_id)->toBe($this->user->id);
    expect($journeyDay->date->format('Y-m-d'))->toBe('2026-04-09');
    expect($journeyDay->operational_approval_status)->toBe('pending');
    expect($journeyDay->hr_approval_status)->toBe('pending');
    expect($journeyDay->is_closed)->toBeFalse();
});

it('enforces unique constraint on tenant + user + date', function () {
    JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);

    JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);
})->throws(QueryException::class);

it('allows same user and date for different tenants', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
    ]);

    JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);

    // Temporariamente desabilitar global scope para criar com outro tenant
    JourneyEntry::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
        'date' => '2026-04-09',
        'regime_type' => 'clt_mensal',
    ]);

    expect(JourneyEntry::withoutGlobalScope('tenant')->count())->toBe(2);
});

it('cannot access journey days from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
    ]);

    $otherJourneyDay = JourneyEntry::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
        'date' => '2026-04-09',
        'regime_type' => 'clt_mensal',
    ]);

    // Com global scope ativo, não deve encontrar
    expect(JourneyEntry::find($otherJourneyDay->id))->toBeNull();
});

it('has many blocks relationship', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    JourneyBlock::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $journeyDay->id,
        'user_id' => $this->user->id,
    ]);

    expect($journeyDay->blocks)->toHaveCount(3);
});

it('blocks are ordered by started_at', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    JourneyBlock::factory()->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $journeyDay->id,
        'user_id' => $this->user->id,
        'started_at' => '2026-04-09 14:00:00',
    ]);

    JourneyBlock::factory()->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $journeyDay->id,
        'user_id' => $this->user->id,
        'started_at' => '2026-04-09 08:00:00',
    ]);

    $blocks = $journeyDay->blocks;

    expect($blocks->first()->started_at->format('H:i'))->toBe('08:00');
    expect($blocks->last()->started_at->format('H:i'))->toBe('14:00');
});

it('recalculates totals from blocks', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'total_minutes_worked' => 0,
        'total_minutes_overtime' => 0,
    ]);

    JourneyBlock::factory()->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $journeyDay->id,
        'user_id' => $this->user->id,
        'classification' => TimeClassificationType::JORNADA_NORMAL->value,
        'duration_minutes' => 240,
        'started_at' => '2026-04-09 08:00:00',
        'ended_at' => '2026-04-09 12:00:00',
    ]);

    JourneyBlock::factory()->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $journeyDay->id,
        'user_id' => $this->user->id,
        'classification' => TimeClassificationType::INTERVALO->value,
        'duration_minutes' => 60,
        'started_at' => '2026-04-09 12:00:00',
        'ended_at' => '2026-04-09 13:00:00',
    ]);

    JourneyBlock::factory()->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $journeyDay->id,
        'user_id' => $this->user->id,
        'classification' => TimeClassificationType::EXECUCAO_SERVICO->value,
        'duration_minutes' => 180,
        'started_at' => '2026-04-09 13:00:00',
        'ended_at' => '2026-04-09 16:00:00',
    ]);

    JourneyBlock::factory()->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $journeyDay->id,
        'user_id' => $this->user->id,
        'classification' => TimeClassificationType::HORA_EXTRA->value,
        'duration_minutes' => 60,
        'started_at' => '2026-04-09 17:00:00',
        'ended_at' => '2026-04-09 18:00:00',
    ]);

    JourneyBlock::factory()->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $journeyDay->id,
        'user_id' => $this->user->id,
        'classification' => TimeClassificationType::DESLOCAMENTO_CLIENTE->value,
        'duration_minutes' => 30,
        'started_at' => '2026-04-09 16:00:00',
        'ended_at' => '2026-04-09 16:30:00',
    ]);

    $journeyDay->recalculateTotals();
    $journeyDay->refresh();

    expect($journeyDay->total_minutes_worked)->toBe(420);      // 240 jornada + 180 execucao
    expect($journeyDay->total_minutes_overtime)->toBe(60);      // 60 hora extra
    expect($journeyDay->total_minutes_break)->toBe(60);         // 60 intervalo
    expect($journeyDay->total_minutes_travel)->toBe(30);        // 30 deslocamento
});

it('correctly reports pending approval status', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'operational_approval_status' => 'pending',
        'hr_approval_status' => 'pending',
    ]);

    expect($journeyDay->isPendingApproval())->toBeTrue();
    expect($journeyDay->isFullyApproved())->toBeFalse();
});

it('correctly reports fully approved status', function () {
    $journeyDay = JourneyEntry::factory()->approved()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    expect($journeyDay->isFullyApproved())->toBeTrue();
    expect($journeyDay->isPendingApproval())->toBeFalse();
});

it('soft deletes journey day', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $journeyDay->delete();

    expect(JourneyEntry::find($journeyDay->id))->toBeNull();
    expect(JourneyEntry::withTrashed()->find($journeyDay->id))->not->toBeNull();
});

it('has correct user relationship', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    expect($journeyDay->user->id)->toBe($this->user->id);
});

it('journey block has correct relationships', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $block = JourneyBlock::factory()->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $journeyDay->id,
        'user_id' => $this->user->id,
        'classification' => TimeClassificationType::EXECUCAO_SERVICO->value,
    ]);

    expect($block->journeyEntry->id)->toBe($journeyDay->id);
    expect($block->user->id)->toBe($this->user->id);
    expect($block->classification)->toBe(TimeClassificationType::EXECUCAO_SERVICO);
    expect($block->isWorkTime())->toBeTrue();
    expect($block->isPaidTime())->toBeTrue();
});

it('journey block calculates duration correctly', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $block = JourneyBlock::factory()->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $journeyDay->id,
        'user_id' => $this->user->id,
        'started_at' => '2026-04-09 08:00:00',
        'ended_at' => '2026-04-09 12:30:00',
    ]);

    expect($block->calculateDuration())->toBe(270); // 4h30m = 270min
});

it('journey block returns 0 duration without ended_at', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $block = JourneyBlock::factory()->create([
        'tenant_id' => $this->tenant->id,
        'journey_entry_id' => $journeyDay->id,
        'user_id' => $this->user->id,
        'started_at' => '2026-04-09 08:00:00',
        'ended_at' => null,
        'duration_minutes' => null,
    ]);

    expect($block->calculateDuration())->toBe(0);
});
