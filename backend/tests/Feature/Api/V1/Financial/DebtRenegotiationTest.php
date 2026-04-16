<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Enums\DebtRenegotiationStatus;
use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\DebtRenegotiation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DebtRenegotiationTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── INDEX ──────────────────────────────────────────────────

    public function test_index_returns_paginated_list(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        DebtRenegotiation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'original_total' => 1000.00,
            'negotiated_total' => 900.00,
            'discount_amount' => 100.00,
            'interest_amount' => 0,
            'new_installments' => 3,
            'first_due_date' => now()->addMonth(),
            'status' => DebtRenegotiationStatus::PENDING,
        ]);

        $response = $this->getJson('/api/v1/debt-renegotiations');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_by_status(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        DebtRenegotiation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'original_total' => 1000.00,
            'negotiated_total' => 900.00,
            'discount_amount' => 100.00,
            'interest_amount' => 0,
            'new_installments' => 3,
            'first_due_date' => now()->addMonth(),
            'status' => DebtRenegotiationStatus::PENDING,
        ]);
        DebtRenegotiation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'original_total' => 2000.00,
            'negotiated_total' => 1800.00,
            'discount_amount' => 200.00,
            'interest_amount' => 0,
            'new_installments' => 6,
            'first_due_date' => now()->addMonth(),
            'status' => DebtRenegotiationStatus::APPROVED,
        ]);

        $response = $this->getJson('/api/v1/debt-renegotiations?status=pending');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_by_search(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Empresa Especifica',
        ]);

        DebtRenegotiation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'original_total' => 1000.00,
            'negotiated_total' => 900.00,
            'discount_amount' => 100.00,
            'interest_amount' => 0,
            'new_installments' => 3,
            'first_due_date' => now()->addMonth(),
            'status' => DebtRenegotiationStatus::PENDING,
        ]);

        $response = $this->getJson('/api/v1/debt-renegotiations?search=Especifica');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_by_description_search(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        DebtRenegotiation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'description' => 'Acordo comercial especial',
            'created_by' => $this->user->id,
            'original_total' => 1000.00,
            'negotiated_total' => 900.00,
            'discount_amount' => 100.00,
            'interest_amount' => 0,
            'new_installments' => 3,
            'first_due_date' => now()->addMonth(),
            'status' => DebtRenegotiationStatus::PENDING,
        ]);

        $response = $this->getJson('/api/v1/debt-renegotiations?search=especial');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    // ── SHOW ──────────────────────────────────────────────────

    public function test_show_returns_renegotiation_details(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $renegotiation = DebtRenegotiation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'original_total' => 5000.00,
            'negotiated_total' => 4500.00,
            'discount_amount' => 500.00,
            'interest_amount' => 0,
            'new_installments' => 3,
            'first_due_date' => '2026-04-15',
            'status' => DebtRenegotiationStatus::PENDING,
        ]);

        $response = $this->getJson("/api/v1/debt-renegotiations/{$renegotiation->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $renegotiation->id)
            ->assertJsonPath('data.original_total', '5000.00')
            ->assertJsonPath('data.negotiated_total', '4500.00');
    }

    public function test_show_blocks_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $renegotiation = DebtRenegotiation::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'original_total' => 1000.00,
            'negotiated_total' => 900.00,
            'discount_amount' => 100.00,
            'interest_amount' => 0,
            'new_installments' => 2,
            'first_due_date' => now()->addMonth(),
            'status' => DebtRenegotiationStatus::PENDING,
        ]);

        $response = $this->getJson("/api/v1/debt-renegotiations/{$renegotiation->id}");

        $response->assertStatus(404);
    }

    // ── STORE ─────────────────────────────────────────────────

    public function test_store_creates_renegotiation_with_items(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $receivable1 = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 500.00,
        ]);
        $receivable2 = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 300.00,
        ]);

        $payload = [
            'customer_id' => $customer->id,
            'receivable_ids' => [$receivable1->id, $receivable2->id],
            'description' => 'Renegociacao do trimestre',
            'new_due_date' => now()->addMonths(2)->format('Y-m-d'),
            'installments' => 4,
            'discount_percentage' => 10,
            'interest_rate' => 0,
            'notes' => 'Renegociação negociada com cliente',
        ];

        $response = $this->postJson('/api/v1/debt-renegotiations', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'tenant_id', 'customer_id', 'original_total', 'negotiated_total', 'discount_amount', 'items'],
            ]);

        $data = $response->json('data');
        // original = 500 + 300 = 800
        $this->assertEquals('800.00', $data['original_total']);
        // 10% discount on 800 = 80, so negotiated = 720
        $this->assertEquals('720.00', $data['negotiated_total']);
        $this->assertEquals('80.00', $data['discount_amount']);
        $this->assertCount(2, $data['items']);

        $this->assertDatabaseHas('debt_renegotiations', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'description' => 'Renegociacao do trimestre',
            'original_total' => 800.00,
            'negotiated_total' => 720.00,
            'new_installments' => 4,
            'status' => DebtRenegotiationStatus::PENDING->value,
        ]);
    }

    public function test_store_uses_open_balance_instead_of_gross_amount(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 400.00,
            'status' => FinancialStatus::PARTIAL,
        ]);

        $response = $this->postJson('/api/v1/debt-renegotiations', [
            'customer_id' => $customer->id,
            'receivable_ids' => [$receivable->id],
            'new_due_date' => now()->addMonth()->format('Y-m-d'),
            'installments' => 2,
            'discount_percentage' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.original_total', '600.00')
            ->assertJsonPath('data.discount_amount', '60.00')
            ->assertJsonPath('data.negotiated_total', '540.00')
            ->assertJsonPath('data.items.0.original_amount', '600.00');
    }

    public function test_store_rejects_receivables_from_different_work_orders(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrderA = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
        $workOrderB = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $receivable1 = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'work_order_id' => $workOrderA->id,
            'status' => FinancialStatus::PENDING,
        ]);
        $receivable2 = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'work_order_id' => $workOrderB->id,
            'status' => FinancialStatus::PENDING,
        ]);

        $this->postJson('/api/v1/debt-renegotiations', [
            'customer_id' => $customer->id,
            'receivable_ids' => [$receivable1->id, $receivable2->id],
            'new_due_date' => now()->addMonth()->format('Y-m-d'),
            'installments' => 2,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['receivable_ids']);
    }

    public function test_store_validation_requires_fields(): void
    {
        $response = $this->postJson('/api/v1/debt-renegotiations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'customer_id',
                'receivable_ids',
                'new_due_date',
                'installments',
            ]);
    }

    public function test_store_validation_rejects_past_due_date(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/debt-renegotiations', [
            'customer_id' => $customer->id,
            'receivable_ids' => [$receivable->id],
            'new_due_date' => '2020-01-01',
            'installments' => 3,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_due_date']);
    }

    public function test_store_validates_customer_from_same_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/debt-renegotiations', [
            'customer_id' => $otherCustomer->id,
            'receivable_ids' => [$receivable->id],
            'new_due_date' => now()->addMonth()->format('Y-m-d'),
            'installments' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    // ── APPROVE ───────────────────────────────────────────────

    public function test_approve_generates_installments_and_marks_originals_as_renegotiated(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $receivable1 = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 600.00,
            'status' => FinancialStatus::PENDING,
        ]);
        $receivable2 = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 400.00,
            'status' => FinancialStatus::PENDING,
        ]);

        // Create renegotiation via API
        $storePayload = [
            'customer_id' => $customer->id,
            'receivable_ids' => [$receivable1->id, $receivable2->id],
            'new_due_date' => now()->addMonth()->format('Y-m-d'),
            'installments' => 3,
            'discount_percentage' => 0,
        ];
        $storeResponse = $this->postJson('/api/v1/debt-renegotiations', $storePayload);
        $storeResponse->assertStatus(201);
        $renegotiationId = $storeResponse->json('data.id');

        // Approve it
        $response = $this->postJson("/api/v1/debt-renegotiations/{$renegotiationId}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);

        $response->assertOk();

        // Original receivables should be marked as renegotiated
        $receivable1->refresh();
        $receivable2->refresh();
        $this->assertEquals(FinancialStatus::RENEGOTIATED, $receivable1->status);
        $this->assertEquals(FinancialStatus::RENEGOTIATED, $receivable2->status);

        // Renegotiation should be approved
        $this->assertDatabaseHas('debt_renegotiations', [
            'id' => $renegotiationId,
            'status' => DebtRenegotiationStatus::APPROVED->value,
            'approved_by' => $this->user->id,
        ]);

        // 3 new installments should exist (total = 1000, so ~333.33 each)
        $newReceivables = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->where('description', 'like', "Renegociação #{$renegotiationId}%")
            ->get();
        $this->assertCount(3, $newReceivables);

        // Sum of installments = original total
        $installmentSum = $newReceivables->sum(fn ($r) => (float) $r->amount);
        $this->assertEquals(1000.00, round($installmentSum, 2));
    }

    public function test_approve_keeps_work_order_link_on_new_installments(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'work_order_id' => $workOrder->id,
            'amount' => 900.00,
            'status' => FinancialStatus::PENDING,
        ]);

        $storeResponse = $this->postJson('/api/v1/debt-renegotiations', [
            'customer_id' => $customer->id,
            'receivable_ids' => [$receivable->id],
            'new_due_date' => now()->addMonth()->format('Y-m-d'),
            'installments' => 3,
        ]);
        $storeResponse->assertCreated();
        $renegotiationId = $storeResponse->json('data.id');

        $this->postJson("/api/v1/debt-renegotiations/{$renegotiationId}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true])
            ->assertOk();

        $newReceivables = AccountReceivable::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('notes', "renegotiation:{$renegotiationId}")
            ->get();

        $this->assertCount(3, $newReceivables);
        $this->assertTrue($newReceivables->every(
            fn (AccountReceivable $item) => (int) $item->work_order_id === $workOrder->id
        ));
    }

    public function test_approve_only_works_on_pending_renegotiations(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $renegotiation = DebtRenegotiation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'original_total' => 1000.00,
            'negotiated_total' => 900.00,
            'discount_amount' => 100.00,
            'interest_amount' => 0,
            'new_installments' => 3,
            'first_due_date' => now()->addMonth(),
            'status' => DebtRenegotiationStatus::APPROVED,
        ]);

        $response = $this->postJson("/api/v1/debt-renegotiations/{$renegotiation->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);

        $response->assertStatus(422);
    }

    public function test_approve_blocks_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $renegotiation = DebtRenegotiation::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'original_total' => 1000.00,
            'negotiated_total' => 900.00,
            'discount_amount' => 100.00,
            'interest_amount' => 0,
            'new_installments' => 3,
            'first_due_date' => now()->addMonth(),
            'status' => DebtRenegotiationStatus::PENDING,
        ]);

        $response = $this->postJson("/api/v1/debt-renegotiations/{$renegotiation->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);

        $response->assertStatus(404);
    }

    // ── CANCEL ────────────────────────────────────────────────

    public function test_cancel_updates_status(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $renegotiation = DebtRenegotiation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'original_total' => 1000.00,
            'negotiated_total' => 900.00,
            'discount_amount' => 100.00,
            'interest_amount' => 0,
            'new_installments' => 3,
            'first_due_date' => now()->addMonth(),
            'status' => DebtRenegotiationStatus::PENDING,
        ]);

        $response = $this->postJson("/api/v1/debt-renegotiations/{$renegotiation->id}/cancel");

        $response->assertOk();
        $this->assertDatabaseHas('debt_renegotiations', [
            'id' => $renegotiation->id,
            'status' => DebtRenegotiationStatus::CANCELLED->value,
        ]);
    }

    public function test_cancel_already_cancelled_returns_422(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $renegotiation = DebtRenegotiation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'original_total' => 1000.00,
            'negotiated_total' => 900.00,
            'discount_amount' => 100.00,
            'interest_amount' => 0,
            'new_installments' => 3,
            'first_due_date' => now()->addMonth(),
            'status' => DebtRenegotiationStatus::CANCELLED,
        ]);

        $response = $this->postJson("/api/v1/debt-renegotiations/{$renegotiation->id}/cancel");

        $response->assertStatus(422);
    }

    // ── AUTH ──────────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/debt-renegotiations');

        $response->assertStatus(401);
    }
}
