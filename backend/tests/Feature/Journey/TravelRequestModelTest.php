<?php

namespace Tests\Feature\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\OvernightStay;
use App\Models\Tenant;
use App\Models\TravelAdvance;
use App\Models\TravelExpenseItem;
use App\Models\TravelExpenseReport;
use App\Models\TravelRequest;
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

it('can create a travel request', function () {
    $request = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'destination' => 'São Paulo',
        'purpose' => 'Manutenção preventiva cliente ABC',
    ]);

    expect($request->destination)->toBe('São Paulo');
    expect($request->status)->toBe('pending');
    expect($request->isPending())->toBeTrue();
    expect($request->user->id)->toBe($this->user->id);
});

it('has overnight stays relationship', function () {
    $request = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    OvernightStay::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'travel_request_id' => $request->id,
        'user_id' => $this->user->id,
    ]);

    expect($request->overnightStays)->toHaveCount(2);
});

it('has advances relationship', function () {
    $request = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    TravelAdvance::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'travel_request_id' => $request->id,
        'user_id' => $this->user->id,
    ]);

    expect($request->advances)->toHaveCount(2);
});

it('calculates total advances paid', function () {
    $request = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    TravelAdvance::factory()->paid()->create([
        'tenant_id' => $this->tenant->id,
        'travel_request_id' => $request->id,
        'user_id' => $this->user->id,
        'amount' => 500,
    ]);

    TravelAdvance::factory()->create([
        'tenant_id' => $this->tenant->id,
        'travel_request_id' => $request->id,
        'user_id' => $this->user->id,
        'amount' => 300,
        'status' => 'pending',
    ]);

    expect($request->totalAdvancesPaid())->toBe(500.0);
});

it('expense report recalculates balance', function () {
    $request = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    TravelAdvance::factory()->paid()->create([
        'tenant_id' => $this->tenant->id,
        'travel_request_id' => $request->id,
        'user_id' => $this->user->id,
        'amount' => 1000,
    ]);

    $report = TravelExpenseReport::factory()->create([
        'tenant_id' => $this->tenant->id,
        'travel_request_id' => $request->id,
        'created_by' => $this->user->id,
    ]);

    TravelExpenseItem::factory()->create([
        'travel_expense_report_id' => $report->id,
        'amount' => 350,
        'type' => 'alimentacao',
    ]);

    TravelExpenseItem::factory()->create([
        'travel_expense_report_id' => $report->id,
        'amount' => 200,
        'type' => 'combustivel',
    ]);

    $report->recalculate();
    $report->refresh();

    expect((float) $report->total_expenses)->toBe(550.0);
    expect((float) $report->total_advances)->toBe(1000.0);
    expect((float) $report->balance)->toBe(450.0); // positive = devolver
});

it('cannot access travel request from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
    ]);

    $otherRequest = TravelRequest::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
        'status' => 'pending',
        'destination' => 'Outro lugar',
        'purpose' => 'Teste',
        'departure_date' => '2026-04-15',
        'return_date' => '2026-04-17',
        'estimated_days' => 2,
    ]);

    expect(TravelRequest::find($otherRequest->id))->toBeNull();
});

it('soft deletes travel request', function () {
    $request = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $request->delete();

    expect(TravelRequest::find($request->id))->toBeNull();
    expect(TravelRequest::withTrashed()->find($request->id))->not->toBeNull();
});

it('overnight stay belongs to travel request', function () {
    $request = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $stay = OvernightStay::factory()->create([
        'tenant_id' => $this->tenant->id,
        'travel_request_id' => $request->id,
        'user_id' => $this->user->id,
        'city' => 'Campinas',
        'cost' => 250.00,
    ]);

    expect($stay->travelRequest->id)->toBe($request->id);
    expect($stay->city)->toBe('Campinas');
    expect((float) $stay->cost)->toBe(250.0);
});
