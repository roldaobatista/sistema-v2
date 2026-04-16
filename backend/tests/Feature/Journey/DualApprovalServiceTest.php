<?php

namespace Tests\Feature\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyApproval;
use App\Models\JourneyEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Journey\DualApprovalService;
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
    $this->manager = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $this->hrUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    app()->instance('current_tenant_id', $this->tenant->id);
    Sanctum::actingAs($this->user, ['*']);
    $this->service = app(DualApprovalService::class);
});

it('submits journey day for approval creating both levels', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);

    $result = $this->service->submitForApproval($journeyDay);

    expect($result->operational_approval_status)->toBe('pending');
    expect($result->hr_approval_status)->toBe('pending');

    $approvals = JourneyApproval::where('journey_entry_id', $journeyDay->id)->get();
    expect($approvals)->toHaveCount(2);
    expect($approvals->pluck('level')->sort()->values()->toArray())->toBe(['hr', 'operational']);
});

it('full flow: pending → operational approved → hr approved → closed', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);

    $this->service->submitForApproval($journeyDay);

    // Step 1: Operational approval
    $afterOp = $this->service->approveOperational($journeyDay, $this->manager, 'Tudo correto');
    expect($afterOp->operational_approval_status)->toBe('approved');
    expect($afterOp->operational_approver_id)->toBe($this->manager->id);
    expect($afterOp->hr_approval_status)->toBe('pending');
    expect($afterOp->is_closed)->toBeFalse();

    // Step 2: HR approval
    $afterHr = $this->service->approveHr($afterOp, $this->hrUser, 'Validado RH');
    expect($afterHr->hr_approval_status)->toBe('approved');
    expect($afterHr->hr_approver_id)->toBe($this->hrUser->id);
    expect($afterHr->is_closed)->toBeTrue();
    expect($afterHr->isFullyApproved())->toBeTrue();
});

it('operational rejection blocks HR advancement', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);

    $this->service->submitForApproval($journeyDay);

    $afterReject = $this->service->rejectOperational($journeyDay, $this->manager, 'Horas inconsistentes');
    expect($afterReject->operational_approval_status)->toBe('rejected');

    // HR cannot approve if operational not approved
    expect(fn () => $this->service->approveHr($afterReject, $this->hrUser))
        ->toThrow(\DomainException::class, 'Aprovação operacional deve ser feita antes');
});

it('HR rejection reopens operational for re-adjustment', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);

    $this->service->submitForApproval($journeyDay);
    $this->service->approveOperational($journeyDay, $this->manager);

    $afterReject = $this->service->rejectHr($journeyDay, $this->hrUser, 'Adicional noturno incorreto');

    expect($afterReject->hr_approval_status)->toBe('rejected');
    expect($afterReject->operational_approval_status)->toBe('pending');
    expect($afterReject->is_closed)->toBeFalse();

    // Operational approval should be reset to pending
    $opApproval = JourneyApproval::where('journey_entry_id', $journeyDay->id)
        ->where('level', 'operational')
        ->first();
    expect($opApproval->status)->toBe('pending');
});

it('submit is idempotent', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);

    $this->service->submitForApproval($journeyDay);
    $this->service->submitForApproval($journeyDay);

    $approvals = JourneyApproval::where('journey_entry_id', $journeyDay->id)->count();
    expect($approvals)->toBe(2); // Still only 2
});

// === Controller Tests ===

it('API: can list pending operational approvals', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
        'operational_approval_status' => 'pending',
    ]);

    $this->getJson('/api/v1/journey/approvals/operational/pending')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('API: can submit day for approval', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);

    $this->postJson("/api/v1/journey/days/{$journeyDay->id}/submit-approval")
        ->assertOk()
        ->assertJsonPath('data.operational_approval_status', 'pending')
        ->assertJsonPath('data.hr_approval_status', 'pending');
});

it('API: can approve operational level', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);
    $this->service->submitForApproval($journeyDay);

    $this->postJson("/api/v1/journey/days/{$journeyDay->id}/approve/operational", [
        'notes' => 'OK',
    ])
        ->assertOk()
        ->assertJsonPath('data.operational_approval_status', 'approved');
});

it('API: can reject with reason', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);
    $this->service->submitForApproval($journeyDay);

    $this->postJson("/api/v1/journey/days/{$journeyDay->id}/reject/operational", [
        'reason' => 'Horas incorretas',
    ])
        ->assertOk()
        ->assertJsonPath('data.operational_approval_status', 'rejected');
});

it('API: reject requires reason (422)', function () {
    $journeyDay = JourneyEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'date' => '2026-04-09',
    ]);
    $this->service->submitForApproval($journeyDay);

    $this->postJson("/api/v1/journey/days/{$journeyDay->id}/reject/operational", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('API: cannot access approval from another tenant', function () {
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

    $this->postJson("/api/v1/journey/days/{$otherDay->id}/approve/operational")
        ->assertNotFound();
});
