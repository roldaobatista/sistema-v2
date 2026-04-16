<?php

namespace Tests\Feature\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\TravelAdvance;
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

// === 1. Sucesso CRUD ===

it('can list travel requests with pagination', function () {
    TravelRequest::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $this->getJson('/api/v1/journey/travel-requests')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'user_id', 'status', 'destination', 'departure_date', 'return_date']],
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
});

it('can show travel request with relationships', function () {
    $tr = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $this->getJson("/api/v1/journey/travel-requests/{$tr->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $tr->id)
        ->assertJsonStructure(['data' => ['id', 'destination', 'purpose', 'status', 'user']]);
});

it('can create a travel request', function () {
    $payload = [
        'user_id' => $this->user->id,
        'destination' => 'Campinas - SP',
        'purpose' => 'Calibração equipamento cliente XYZ',
        'departure_date' => now()->addDay()->toDateString(),
        'return_date' => now()->addDays(3)->toDateString(),
        'estimated_days' => 3,
        'requires_vehicle' => true,
        'requires_overnight' => true,
        'overtime_authorized' => false,
        'daily_allowance_amount' => 150.00,
    ];

    $this->postJson('/api/v1/journey/travel-requests', $payload)
        ->assertCreated()
        ->assertJsonPath('data.destination', 'Campinas - SP')
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('travel_requests', [
        'destination' => 'Campinas - SP',
        'tenant_id' => $this->tenant->id,
    ]);
});

it('can approve a travel request', function () {
    $tr = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'status' => 'pending',
    ]);

    $this->postJson("/api/v1/journey/travel-requests/{$tr->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
});

it('can cancel a travel request', function () {
    $tr = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'status' => 'approved',
    ]);

    $this->postJson("/api/v1/journey/travel-requests/{$tr->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('can delete a pending travel request', function () {
    $tr = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'status' => 'pending',
    ]);

    $this->deleteJson("/api/v1/journey/travel-requests/{$tr->id}")
        ->assertNoContent();
});

// === 2. Validação 422 ===

it('fails validation when required fields are missing', function () {
    $this->postJson('/api/v1/journey/travel-requests', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['destination', 'purpose', 'departure_date', 'return_date', 'user_id']);
});

it('rejects travel request for cross-tenant user', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
    ]);
    $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);

    $payload = [
        'user_id' => $otherUser->id,
        'destination' => 'Campinas - SP',
        'purpose' => 'Calibração equipamento cliente XYZ',
        'departure_date' => '2026-04-15',
        'return_date' => '2026-04-17',
        'estimated_days' => 3,
        'requires_vehicle' => true,
        'requires_overnight' => true,
        'overtime_authorized' => false,
    ];

    $this->postJson('/api/v1/journey/travel-requests', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);

    $this->assertDatabaseMissing('travel_requests', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $otherUser->id,
        'destination' => 'Campinas - SP',
    ]);
});

it('cannot approve non-pending request', function () {
    $tr = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'status' => 'completed',
    ]);

    $this->postJson("/api/v1/journey/travel-requests/{$tr->id}/approve")
        ->assertStatus(422);
});

// === 3. Cross-tenant 404 ===

it('cannot access travel request from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
    ]);

    $otherTr = TravelRequest::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
        'status' => 'pending',
        'destination' => 'Other',
        'purpose' => 'Test',
        'departure_date' => '2026-04-20',
        'return_date' => '2026-04-22',
        'estimated_days' => 2,
        'requires_vehicle' => false,
        'requires_overnight' => false,
        'overtime_authorized' => false,
    ]);

    $this->getJson("/api/v1/journey/travel-requests/{$otherTr->id}")
        ->assertNotFound();
});

// === 4. Expense Report ===

it('can submit expense report', function () {
    $tr = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'status' => 'in_progress',
    ]);

    $payload = [
        'items' => [
            ['type' => 'alimentacao', 'description' => 'Almoço dia 1', 'amount' => 45.00, 'expense_date' => '2026-04-15'],
            ['type' => 'combustivel', 'description' => 'Abastecimento', 'amount' => 200.00, 'expense_date' => '2026-04-15'],
            ['type' => 'hospedagem', 'description' => 'Hotel 1 noite', 'amount' => 180.00, 'expense_date' => '2026-04-15'],
        ],
    ];

    $this->postJson("/api/v1/journey/travel-requests/{$tr->id}/expense-report", $payload)
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    $report = TravelExpenseReport::where('travel_request_id', $tr->id)->first();
    expect($report)->not->toBeNull();
    expect($report->items)->toHaveCount(3);
    expect((float) $report->total_expenses)->toBe(425.0);
});

// === 5. Edge case ===

it('expense report recalculates with advances', function () {
    $tr = TravelRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    TravelAdvance::factory()->paid()->create([
        'tenant_id' => $this->tenant->id,
        'travel_request_id' => $tr->id,
        'user_id' => $this->user->id,
        'amount' => 500,
    ]);

    $payload = [
        'items' => [
            ['type' => 'alimentacao', 'description' => 'Refeições', 'amount' => 200.00, 'expense_date' => '2026-04-15'],
        ],
    ];

    $response = $this->postJson("/api/v1/journey/travel-requests/{$tr->id}/expense-report", $payload);
    $response->assertOk();

    $report = TravelExpenseReport::where('travel_request_id', $tr->id)->first();
    expect((float) $report->balance)->toBe(300.0); // 500 advance - 200 expenses = 300 to return
});
