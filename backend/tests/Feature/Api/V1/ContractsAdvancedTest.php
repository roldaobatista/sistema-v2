<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractsAdvancedTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
    }

    private function createContract(array $overrides = []): int
    {
        return DB::table('recurring_contracts')->insertGetId(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'name' => 'Contract Test',
            'frequency' => 'monthly',
            'monthly_value' => 1000.00,
            'is_active' => true,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'next_run_date' => now()->addMonth()->toDateString(),
            'adjustment_index' => 'IGPM',
            'next_adjustment_date' => now()->addDays(15)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ─── PENDING ADJUSTMENTS ──────────────────────────

    public function test_pending_adjustments_returns_contracts_due_for_adjustment(): void
    {
        $this->createContract([
            'next_adjustment_date' => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/contracts-advanced/adjustments/pending');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['pending_count', 'contracts'],
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('data.pending_count'));
    }

    public function test_pending_adjustments_excludes_inactive_contracts(): void
    {
        $this->createContract([
            'is_active' => false,
            'next_adjustment_date' => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/contracts-advanced/adjustments/pending');
        $response->assertStatus(200);
        // Inactive contract should not appear
        $contracts = collect($response->json('data.contracts'));
        $contracts->each(function ($c) {
            $this->assertTrue((bool) $c['is_active']);
        });
    }

    // ─── APPLY ADJUSTMENT ─────────────────────────────

    public function test_apply_adjustment_calculates_new_value(): void
    {
        $contractId = $this->createContract([
            'monthly_value' => 1000.00,
        ]);

        $response = $this->postJson("/api/v1/contracts-advanced/{$contractId}/adjust", [
            'index_rate' => 10, // 10%
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(1000.00, $data['old_value']);
        $this->assertEquals(1100.00, $data['new_value']);
        $this->assertEquals(10, $data['change_percent']);

        // Verify contract was updated
        $contract = DB::table('recurring_contracts')->find($contractId);
        $this->assertEquals(1100.00, $contract->monthly_value);

        // Verify adjustment record was created
        $this->assertDatabaseHas('contract_adjustments', [
            'contract_id' => $contractId,
            'old_value' => 1000.00,
            'new_value' => 1100.00,
        ]);
    }

    public function test_apply_adjustment_rejects_rate_out_of_range(): void
    {
        $contractId = $this->createContract();

        $response = $this->postJson("/api/v1/contracts-advanced/{$contractId}/adjust", [
            'index_rate' => 150, // over 100%
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['index_rate']);
    }

    public function test_apply_adjustment_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $contractId = DB::table('recurring_contracts')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $otherUser->id,
            'name' => 'Other Contract',
            'frequency' => 'monthly',
            'monthly_value' => 500.00,
            'is_active' => true,
            'start_date' => now()->subYear()->toDateString(),
            'next_run_date' => now()->addMonth()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/contracts-advanced/{$contractId}/adjust", [
            'index_rate' => 5,
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(404);
    }

    public function test_apply_adjustment_requires_effective_date(): void
    {
        $contractId = $this->createContract();

        $response = $this->postJson("/api/v1/contracts-advanced/{$contractId}/adjust", [
            'index_rate' => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['effective_date']);
    }

    // ─── CHURN RISK ───────────────────────────────────

    public function test_churn_risk_returns_expiring_contracts(): void
    {
        $this->createContract([
            'end_date' => now()->addDays(20)->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/contracts-advanced/churn-risk?days=30');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_at_risk',
                'total_mrr_at_risk',
                'by_risk_level' => ['critical', 'high', 'medium'],
                'contracts',
            ],
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('data.total_at_risk'));
    }

    public function test_churn_risk_categorizes_risk_levels(): void
    {
        // Critical: <= 15 days
        $this->createContract([
            'end_date' => now()->addDays(10)->toDateString(),
        ]);
        // High: 16-30 days
        $this->createContract([
            'end_date' => now()->addDays(25)->toDateString(),
        ]);
        // Medium: 31-60 days
        $this->createContract([
            'end_date' => now()->addDays(45)->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/contracts-advanced/churn-risk?days=60');
        $response->assertStatus(200);

        $byRisk = $response->json('data.by_risk_level');
        $this->assertGreaterThanOrEqual(1, $byRisk['critical']);
        $this->assertGreaterThanOrEqual(1, $byRisk['high']);
        $this->assertGreaterThanOrEqual(1, $byRisk['medium']);
    }

    // ─── ADDENDUMS ────────────────────────────────────

    public function test_create_addendum_and_list(): void
    {
        $contractId = $this->createContract();

        // Create addendum
        $createResponse = $this->postJson("/api/v1/contracts-advanced/{$contractId}/addendums", [
            'type' => 'value_change',
            'description' => 'Aumento por inclusão de serviço',
            'new_value' => 1500.00,
            'effective_date' => now()->addDays(30)->toDateString(),
        ]);
        $createResponse->assertStatus(201);
        $addendumId = $createResponse->json('data.id');

        // Verify pending status
        $this->assertDatabaseHas('contract_addendums', [
            'id' => $addendumId,
            'status' => 'pending',
            'type' => 'value_change',
        ]);

        // List addendums
        $listResponse = $this->getJson("/api/v1/contracts-advanced/{$contractId}/addendums");
        $listResponse->assertStatus(200);
    }

    public function test_create_addendum_validates_type(): void
    {
        $contractId = $this->createContract();

        $response = $this->postJson("/api/v1/contracts-advanced/{$contractId}/addendums", [
            'type' => 'invalid_type',
            'description' => 'Test',
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_approve_addendum_applies_changes_to_contract(): void
    {
        $contractId = $this->createContract(['monthly_value' => 1000.00]);

        $addendumId = DB::table('contract_addendums')->insertGetId([
            'contract_id' => $contractId,
            'tenant_id' => $this->tenant->id,
            'type' => 'value_change',
            'description' => 'Value increase',
            'new_value' => 1500.00,
            'effective_date' => now()->toDateString(),
            'status' => 'pending',
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/contracts-advanced/addendums/{$addendumId}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);
        $response->assertStatus(200);

        // Verify addendum was approved
        $addendum = DB::table('contract_addendums')->find($addendumId);
        $this->assertEquals('approved', $addendum->status);
        $this->assertNotNull($addendum->approved_at);

        // Verify contract value was updated
        $contract = DB::table('recurring_contracts')->find($contractId);
        $this->assertEquals(1500.00, $contract->monthly_value);
    }

    public function test_approve_cancellation_addendum_deactivates_contract(): void
    {
        $contractId = $this->createContract(['is_active' => true]);

        $addendumId = DB::table('contract_addendums')->insertGetId([
            'contract_id' => $contractId,
            'tenant_id' => $this->tenant->id,
            'type' => 'cancellation',
            'description' => 'Customer requested cancellation',
            'effective_date' => now()->toDateString(),
            'status' => 'pending',
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/contracts-advanced/addendums/{$addendumId}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);
        $response->assertStatus(200);

        $contract = DB::table('recurring_contracts')->find($contractId);
        $this->assertFalse((bool) $contract->is_active);
    }

    public function test_approve_addendum_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $addendumId = DB::table('contract_addendums')->insertGetId([
            'contract_id' => 999,
            'tenant_id' => $otherTenant->id,
            'type' => 'value_change',
            'description' => 'Other tenant',
            'effective_date' => now()->toDateString(),
            'status' => 'pending',
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/contracts-advanced/addendums/{$addendumId}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);
        $response->assertStatus(404);
    }

    // ─── MEASUREMENTS ─────────────────────────────────

    public function test_store_measurement_calculates_totals(): void
    {
        $contractId = $this->createContract();

        $response = $this->postJson("/api/v1/contracts-advanced/{$contractId}/measurements", [
            'period' => '2026-03',
            'items' => [
                ['description' => 'Calibração Eq. A', 'quantity' => 2, 'unit_price' => 100.00, 'accepted' => true],
                ['description' => 'Calibração Eq. B', 'quantity' => 1, 'unit_price' => 150.00, 'accepted' => true],
                ['description' => 'Manutenção Eq. C', 'quantity' => 1, 'unit_price' => 200.00, 'accepted' => false],
            ],
            'notes' => 'Medição mensal',
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals(350.00, $data['total_accepted']); // 2*100 + 1*150
        $this->assertEquals(200.00, $data['total_rejected']); // 1*200
    }

    public function test_store_measurement_requires_items(): void
    {
        $contractId = $this->createContract();

        $response = $this->postJson("/api/v1/contracts-advanced/{$contractId}/measurements", [
            'period' => '2026-03',
            'items' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_store_measurement_validates_item_fields(): void
    {
        $contractId = $this->createContract();

        $response = $this->postJson("/api/v1/contracts-advanced/{$contractId}/measurements", [
            'period' => '2026-03',
            'items' => [
                ['description' => '', 'quantity' => -1, 'unit_price' => -50],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_list_measurements_returns_paginated(): void
    {
        $contractId = $this->createContract();

        DB::table('contract_measurements')->insert([
            'contract_id' => $contractId,
            'tenant_id' => $this->tenant->id,
            'period' => '2026-02',
            'items' => json_encode([]),
            'total_accepted' => 500,
            'total_rejected' => 0,
            'status' => 'pending_approval',
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/contracts-advanced/{$contractId}/measurements");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
                'current_page',
                'per_page',
                'total',
            ]);
        $this->assertCount(1, $response->json('data'));
    }

    // ─── NEGATIVE ADJUSTMENT (discount) ───────────────

    public function test_apply_negative_adjustment_reduces_value(): void
    {
        $contractId = $this->createContract(['monthly_value' => 2000.00]);

        $response = $this->postJson("/api/v1/contracts-advanced/{$contractId}/adjust", [
            'index_rate' => -10, // -10%
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1800.00, $response->json('data.new_value'));
    }
}
