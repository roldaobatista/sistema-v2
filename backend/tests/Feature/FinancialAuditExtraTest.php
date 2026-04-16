<?php

namespace Tests\Feature;

use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\BankStatementEntry;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BankReconciliationService;
use App\Services\CashFlowProjectionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Extra audit tests covering edge cases not addressed by existing test suites:
 * - Overpayment blocking (AP & AR)
 * - recalculateStatus → overdue transition
 * - Payment on cancelled title blocked
 * - BankReconciliationService::calculateScore bcmath precision
 * - CashFlowProjection with zero data returns formatted strings
 */
class FinancialAuditExtraTest extends TestCase
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
            'is_active' => true,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── Overpayment Blocking ──

    public function test_overpayment_blocked_on_account_receivable(): void
    {
        $ar = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Titulo para testar overpayment',
            'amount' => 1000.00,
            'amount_paid' => 0,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        $response = $this->postJson("/api/v1/accounts-receivable/{$ar->id}/pay", [
            'amount' => 1500.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $ar->refresh();
        $this->assertSame(0.0, (float) $ar->amount_paid);
    }

    public function test_overpayment_blocked_on_account_payable(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 500.00,
            'amount_paid' => 0,
            'status' => AccountPayable::STATUS_PENDING,
        ]);

        $response = $this->postJson("/api/v1/accounts-payable/{$ap->id}/pay", [
            'amount' => 800.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $ap->refresh();
        $this->assertSame(0.0, (float) $ap->amount_paid);
    }

    // ── recalculateStatus: Overdue Detection ──

    public function test_recalculate_status_marks_overdue_when_past_due(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => 2000.00,
            'amount_paid' => 500.00,
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => FinancialStatus::PENDING,
        ]);

        $ar->recalculateStatus();
        $ar->refresh();

        $this->assertEquals(FinancialStatus::OVERDUE, $ar->status);
    }

    public function test_recalculate_status_marks_overdue_for_payable_past_due(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 3000.00,
            'amount_paid' => 0,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => FinancialStatus::PENDING,
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::OVERDUE, $ap->status);
    }

    // ── Payment on Cancelled Title Blocked ──

    public function test_payment_on_cancelled_receivable_is_blocked(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => FinancialStatus::CANCELLED,
        ]);

        $response = $this->postJson("/api/v1/accounts-receivable/{$ar->id}/pay", [
            'amount' => 500.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_payment_on_cancelled_payable_is_blocked(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => FinancialStatus::CANCELLED,
        ]);

        $response = $this->postJson("/api/v1/accounts-payable/{$ap->id}/pay", [
            'amount' => 500.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    // ── BankReconciliationService::calculateScore — bcmath precision ──

    public function test_calculate_score_exact_match_gives_high_score(): void
    {
        $service = app(BankReconciliationService::class);

        $entry = new BankStatementEntry([
            'amount' => '1500.00',
            'date' => '2026-01-15',
            'description' => 'Pagamento calibracao balanca',
        ]);

        $record = new AccountReceivable([
            'amount' => '1500.00',
            'due_date' => '2026-01-15',
            'description' => 'Pagamento calibracao balanca',
        ]);

        $reflection = new \ReflectionMethod($service, 'calculateScore');
        $score = $reflection->invoke($service, $entry, $record);

        // Exact match on value (50) + date (30) + description (20) = 100
        $this->assertGreaterThanOrEqual(95.0, $score);
    }

    public function test_calculate_score_different_values_gives_lower_score(): void
    {
        $service = app(BankReconciliationService::class);

        $entry = new BankStatementEntry([
            'amount' => '1000.00',
            'date' => '2026-01-15',
            'description' => 'Pagamento',
        ]);

        $record = new AccountReceivable([
            'amount' => '500.00',
            'due_date' => '2026-01-15',
            'description' => 'Pagamento',
        ]);

        $reflection = new \ReflectionMethod($service, 'calculateScore');
        $score = $reflection->invoke($service, $entry, $record);

        $this->assertLessThan(80.0, $score);
    }

    public function test_calculate_score_zero_entry_amount_does_not_divide_by_zero(): void
    {
        $service = app(BankReconciliationService::class);

        $entry = new BankStatementEntry([
            'amount' => '0.00',
            'date' => '2026-01-15',
            'description' => 'Zerado',
        ]);

        $record = new AccountReceivable([
            'amount' => '100.00',
            'due_date' => '2026-01-15',
            'description' => 'Zerado',
        ]);

        $reflection = new \ReflectionMethod($service, 'calculateScore');
        $score = $reflection->invoke($service, $entry, $record);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0.0, $score);
    }

    // ── CashFlowProjection: Formatted Strings ──

    public function test_cash_flow_projection_with_no_data_returns_formatted_strings(): void
    {
        $service = app(CashFlowProjectionService::class);

        $from = Carbon::parse('2099-01-01');
        $to = Carbon::parse('2099-01-31');

        $result = $service->project($from, $to, $this->tenant->id);

        $this->assertSame('0.00', $result['summary']['entradas_previstas']);
        $this->assertSame('0.00', $result['summary']['saidas_previstas']);
        $this->assertSame('0.00', $result['summary']['saldo_previsto']);
        $this->assertSame('0.00', $result['summary']['entradas_realizadas']);
        $this->assertSame('0.00', $result['summary']['saidas_realizadas']);
    }

    // ── recalculateStatus preserves terminal status ──

    public function test_recalculate_status_preserves_paid_status(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 1000.00,
            'status' => FinancialStatus::PAID,
            'paid_at' => now(),
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::PAID, $ap->status);
    }

    public function test_recalculate_status_preserves_cancelled_for_payable(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => FinancialStatus::CANCELLED,
        ]);

        $ap->recalculateStatus();
        $ap->refresh();

        $this->assertEquals(FinancialStatus::CANCELLED, $ap->status);
    }
}
