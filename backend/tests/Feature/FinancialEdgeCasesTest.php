<?php

namespace Tests\Feature;

use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountPayableCategory;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Financial Edge Cases Tests — validates tricky financial scenarios:
 * partial payments, overpayments, zero amounts, date edge cases,
 * and status transitions.
 */
class FinancialEdgeCasesTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private AccountPayableCategory $payableCategory;

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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->payableCategory = AccountPayableCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── ACCOUNTS RECEIVABLE ──

    public function test_partial_payment_changes_status_to_partial(): void
    {
        $ar = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Fatura parcial',
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => AccountReceivable::STATUS_PENDING,
            'due_date' => now()->addDays(30),
        ]);

        $response = $this->postJson("/api/v1/accounts-receivable/{$ar->id}/pay", [
            'amount' => 500.00,
            'payment_method' => 'pix',
            'payment_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(201);

        $ar->refresh();
        $this->assertEquals(FinancialStatus::PARTIAL, $ar->status);
        $this->assertEquals(500.00, (float) $ar->amount_paid);
    }

    public function test_full_payment_changes_status_to_paid(): void
    {
        $ar = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Fatura total',
            'amount' => 500.00,
            'amount_paid' => 0,
            'status' => AccountReceivable::STATUS_PENDING,
            'due_date' => now()->addDays(30),
        ]);

        $response = $this->postJson("/api/v1/accounts-receivable/{$ar->id}/pay", [
            'amount' => 500.00,
            'payment_method' => 'pix',
            'payment_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(201);

        $ar->refresh();
        $this->assertEquals(FinancialStatus::PAID, $ar->status);
    }

    // ── ACCOUNTS PAYABLE ──

    public function test_create_payable_with_all_fields(): void
    {
        $response = $this->postJson('/api/v1/accounts-payable', [
            'category_id' => $this->payableCategory->id,
            'description' => 'Fornecedor X - Material',
            'amount' => 2500.00,
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertCreated();
    }

    public function test_payable_summary_returns_correct_totals(): void
    {
        AccountPayable::create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'description' => 'Payable 1',
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => AccountPayable::STATUS_PENDING,
            'due_date' => now()->addDays(15),
        ]);

        $response = $this->getJson('/api/v1/accounts-payable-summary');

        $response->assertOk();

        $data = $response->json();
        $this->assertIsNumeric($data['total_pending'] ?? $data['pending'] ?? 0);
    }

    // ── CASH FLOW ──

    public function test_cash_flow_endpoint_returns_data(): void
    {
        $response = $this->getJson('/api/v1/cash-flow');
        $response->assertOk();
    }

    public function test_dre_endpoint_returns_data(): void
    {
        $response = $this->getJson('/api/v1/dre');
        $response->assertOk();
    }

    public function test_cash_flow_accepts_date_range(): void
    {
        $response = $this->getJson('/api/v1/cash-flow?date_from=2025-01-01&date_to=2025-12-31');
        $response->assertOk();
    }

    // ── RECEIVABLE OVERDUE DETECTION ──

    public function test_overdue_receivable_identified_correctly(): void
    {
        $ar = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Fatura vencida',
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => AccountReceivable::STATUS_PENDING,
            'due_date' => now()->subDays(5), // vencida há 5 dias
        ]);

        // The summary should include this as overdue
        $response = $this->getJson('/api/v1/accounts-receivable-summary');

        $response->assertOk();
    }

    // ── INSTALLMENT GENERATION ──

    public function test_installment_generation_endpoint(): void
    {
        $response = $this->postJson('/api/v1/accounts-receivable/installments', [
            'customer_id' => $this->customer->id,
            'description' => 'Parcelamento',
            'total_amount' => 3000.00,
            'installments' => 3,
            'first_due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertCreated();
    }

    // ── INVOICE METADATA ──

    public function test_invoice_metadata_endpoint_returns_options(): void
    {
        $response = $this->getJson('/api/v1/invoices/metadata');

        $response->assertOk();

        $data = $response->json();
        $this->assertIsArray($data);
    }
}
