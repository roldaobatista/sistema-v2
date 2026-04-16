<?php

namespace Tests\Feature;

use App\Enums\FinancialStatus;
use App\Enums\InvoiceStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayableCategory;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Professional Financial Flow tests — verifies exact money calculations,
 * partial payments, balance updates, and status transitions in the financial module.
 */
class FinancialFlowProfessionalTest extends TestCase
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
            'is_active' => true,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->payableCategory = AccountPayableCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── ACCOUNTS RECEIVABLE: Pagamento Parcial ──

    public function test_partial_payment_updates_amount_paid_and_keeps_pending(): void
    {
        $receivable = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Serviço de calibração',
            'amount' => 1000.00,
            'amount_paid' => 0,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
            'amount' => 400.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);

        $receivable->refresh();
        $this->assertSame(400.0, (float) $receivable->amount_paid);
        $this->assertNull($receivable->paid_at);

        // Payment should be recorded
        $this->assertDatabaseHas('payments', [
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'amount' => 400.00,
            'payment_method' => 'pix',
        ]);
    }

    public function test_full_payment_sets_status_paid_and_paid_at(): void
    {
        $receivable = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Fatura completa',
            'amount' => 500.00,
            'amount_paid' => 0,
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => 'pending',
        ]);

        $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
            'amount' => 500.00,
            'payment_method' => 'boleto',
            'payment_date' => now()->toDateString(),
        ])->assertStatus(201);

        $receivable->refresh();
        $this->assertSame(500.0, (float) $receivable->amount_paid);
        $this->assertEquals(FinancialStatus::PAID, $receivable->status);
        $this->assertNotNull($receivable->paid_at);
    }

    public function test_partial_payment_on_overdue_keeps_overdue_status(): void
    {
        $receivable = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Recebível vencido',
            'amount' => 900.00,
            'amount_paid' => 0,
            'due_date' => now()->subDay()->toDateString(),
            'status' => 'pending',
        ]);

        $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
            'amount' => 300.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertStatus(201);

        $receivable->refresh();
        $this->assertTrue($receivable->status->value === 'overdue' || $receivable->status === 'overdue');
        $this->assertNull($receivable->paid_at);
        $this->assertSame(300.0, (float) $receivable->amount_paid);
    }

    public function test_partial_payments_release_commission_proportionally_without_drift(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_INVOICED,
            'total' => 1000.00,
        ]);

        $receivable = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $workOrder->id,
            'created_by' => $this->user->id,
            'description' => 'Titulo com comissao por recebimento',
            'amount' => 1000.00,
            'amount_paid' => 0,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Comissao por recebimento',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_INSTALLMENT_PAID,
        ]);

        $pendingEvent = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000.00,
            'commission_amount' => 100.00,
            'proportion' => 1.0000,
            'status' => CommissionEvent::STATUS_PENDING,
            'notes' => 'Regra: Comissao por recebimento (percent_gross) | trigger:installment_paid',
        ]);

        $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
            'amount' => 200.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertStatus(201);

        $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
            'amount' => 300.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertStatus(201);

        $pendingEvent->refresh();
        $approvedEvents = CommissionEvent::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('work_order_id', $workOrder->id)
            ->where('status', CommissionEvent::STATUS_APPROVED)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $approvedEvents);
        $this->assertSame(20.0, (float) $approvedEvents[0]->commission_amount);
        $this->assertSame(30.0, (float) $approvedEvents[1]->commission_amount);
        $this->assertSame(50.0, (float) $pendingEvent->commission_amount);
        $this->assertSame(500.0, (float) $pendingEvent->base_amount);
    }

    public function test_payment_reversal_restores_pending_commission_balance(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_INVOICED,
            'total' => 1000.00,
        ]);

        $receivable = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $workOrder->id,
            'created_by' => $this->user->id,
            'description' => 'Titulo com estorno de comissao',
            'amount' => 1000.00,
            'amount_paid' => 0,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Comissao por recebimento com estorno',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_INSTALLMENT_PAID,
        ]);

        $pendingEvent = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'base_amount' => 1000.00,
            'commission_amount' => 100.00,
            'proportion' => 1.0000,
            'status' => CommissionEvent::STATUS_PENDING,
            'notes' => 'Regra: Comissao por recebimento (percent_gross) | trigger:installment_paid',
        ]);

        $paymentResponse = $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
            'amount' => 200.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertStatus(201);

        $approvedEvent = CommissionEvent::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('work_order_id', $workOrder->id)
            ->where('status', CommissionEvent::STATUS_APPROVED)
            ->firstOrFail();

        $this->deleteJson('/api/v1/payments/'.$paymentResponse->json('data.id'))
            ->assertOk();

        $pendingEvent->refresh();
        $approvedEvent->refresh();
        $receivable->refresh();

        $this->assertSame(0.0, (float) $receivable->amount_paid);
        $this->assertSame(AccountReceivable::STATUS_PENDING, $receivable->status->value);
        $this->assertSame(CommissionEvent::STATUS_REVERSED, $approvedEvent->status->value);
        $this->assertSame(100.0, (float) $pendingEvent->commission_amount);
        $this->assertSame(1000.0, (float) $pendingEvent->base_amount);
    }

    // ── ACCOUNTS PAYABLE: Fluxo similar ──

    public function test_create_account_payable_with_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/accounts-payable', [
            'category_id' => $this->payableCategory->id,
            'supplier_id' => $supplier->id,
            'description' => 'Compra de insumos',
            'amount' => 2500.00,
            'due_date' => now()->addDays(45)->toDateString(),
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('accounts_payable', [
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'description' => 'Compra de insumos',
            'amount' => 2500.00,
            'status' => 'pending',
        ]);
    }

    // ── INVOICE: Lifecycle ──

    public function test_invoice_from_work_order_transitions_wo_to_invoiced(): void
    {
        $wo = WorkOrder::factory()->delivered()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'total' => 3000.00,
        ]);

        $response = $this->postJson('/api/v1/invoices', [
            'customer_id' => $this->customer->id,
            'work_order_id' => $wo->id,
        ]);

        $response->assertStatus(201);

        // WO must transition to invoiced
        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'status' => 'invoiced',
        ]);

        // Invoice should reference the WO
        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'status' => 'draft',
        ]);
    }

    public function test_cancel_invoice_reverts_work_order_to_delivered(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_INVOICED,
        ]);

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'work_order_id' => $wo->id,
        ]);

        $response = $this->putJson("/api/v1/invoices/{$invoice->id}", [
            'status' => 'cancelled',
        ]);
        $response->assertOk();

        $wo->refresh();
        $this->assertSame(WorkOrder::STATUS_DELIVERED, $wo->status);
    }

    public function test_cancel_invoice_cancels_receivables_and_reverses_invoiced_commissions(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_INVOICED,
        ]);

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'work_order_id' => $wo->id,
        ]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'work_order_id' => $wo->id,
            'invoice_id' => $invoice->id,
            'amount' => 900.00,
            'amount_paid' => 0,
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Comissao faturamento',
            'type' => 'percentage',
            'value' => 5,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_INVOICED,
        ]);

        $commission = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->user->id,
            'base_amount' => 900.00,
            'commission_amount' => 45.00,
            'proportion' => 1.0000,
            'status' => CommissionEvent::STATUS_PENDING,
            'notes' => 'Regra: Comissao faturamento (percent_gross) | trigger:os_invoiced',
        ]);

        $this->putJson("/api/v1/invoices/{$invoice->id}", [
            'status' => Invoice::STATUS_CANCELLED,
        ])->assertOk();

        $wo->refresh();
        $receivable->refresh();
        $commission->refresh();

        $this->assertSame(WorkOrder::STATUS_DELIVERED, $wo->status);
        $this->assertSame(AccountReceivable::STATUS_CANCELLED, $receivable->status->value);
        // Pending events are cancelled (not reversed) — reversal only applies to already-paid events
        $this->assertSame(CommissionEvent::STATUS_CANCELLED, $commission->status->value);
    }

    public function test_cancel_invoice_with_partial_receivable_is_blocked(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_INVOICED,
        ]);

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'work_order_id' => $wo->id,
        ]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'work_order_id' => $wo->id,
            'invoice_id' => $invoice->id,
            'amount' => 900.00,
            'amount_paid' => 200.00,
            'status' => AccountReceivable::STATUS_PARTIAL,
        ]);

        $this->putJson("/api/v1/invoices/{$invoice->id}", [
            'status' => Invoice::STATUS_CANCELLED,
        ])->assertStatus(422);

        $wo->refresh();
        $invoice->refresh();
        $receivable->refresh();

        $this->assertSame(WorkOrder::STATUS_INVOICED, $wo->status);
        $this->assertSame(InvoiceStatus::ISSUED, $invoice->status);
        $this->assertSame(AccountReceivable::STATUS_PARTIAL, $receivable->status->value);
    }

    public function test_invoice_invalid_status_transition_blocked(): void
    {
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $this->putJson("/api/v1/invoices/{$invoice->id}", [
            'status' => 'draft',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Transição de status inválida: issued -> draft');
    }

    public function test_cancelled_invoice_cannot_be_edited(): void
    {
        $invoice = Invoice::factory()->cancelled()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $this->putJson("/api/v1/invoices/{$invoice->id}", [
            'nf_number' => '99999',
        ])->assertStatus(422);
    }

    // ── SUMMARY: Exact Financial Totals ──

    public function test_receivable_summary_calculates_exact_open_balance(): void
    {
        AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Parcial futuro',
            'amount' => 1000,
            'amount_paid' => 300,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'partial',
        ]);

        AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Vencido',
            'amount' => 500,
            'amount_paid' => 100,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'overdue',
        ]);

        $response = $this->getJson('/api/v1/accounts-receivable-summary');

        $response->assertOk();
        // Total open = (1000-300) + (500-100) = 1100
        $this->assertSame(1100.0, (float) $response->json('data.total_open'));
    }

    // ── CROSS-TENANT: Foreign entities rejected ──

    public function test_create_receivable_rejects_foreign_tenant_customer(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/accounts-receivable', [
            'customer_id' => $foreignCustomer->id,
            'description' => 'Teste fora do tenant',
            'amount' => 100,
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_create_payable_rejects_foreign_tenant_supplier(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignSupplier = Supplier::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/accounts-payable', [
            'supplier_id' => $foreignSupplier->id,
            'description' => 'Conta inválida',
            'amount' => 200,
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_id']);
    }
}
