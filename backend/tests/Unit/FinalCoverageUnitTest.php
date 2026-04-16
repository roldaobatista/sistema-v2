<?php

namespace Tests\Unit;

use App\Enums\DealStatus;
use App\Enums\ExpenseStatus;
use App\Enums\FinancialStatus;
use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Enums\WorkOrderStatus;
use App\Services\Calibration\EmaCalculator;
use App\Services\Fiscal\FiscalResult;
use PHPUnit\Framework\TestCase;

/**
 * Testes finais de cobertura: combinações de enums,
 * cross-enum operations, fiscal helpers, EMA edge cases.
 */
class FinalCoverageUnitTest extends TestCase
{
    // ═══ Enum from value roundtrips ═══

    public function test_all_financial_statuses_roundtrip(): void
    {
        foreach (FinancialStatus::cases() as $case) {
            $recovered = FinancialStatus::from($case->value);
            $this->assertEquals($case, $recovered);
        }
    }

    public function test_all_wo_statuses_roundtrip(): void
    {
        foreach (WorkOrderStatus::cases() as $case) {
            $recovered = WorkOrderStatus::from($case->value);
            $this->assertEquals($case, $recovered);
        }
    }

    public function test_all_quote_statuses_roundtrip(): void
    {
        foreach (QuoteStatus::cases() as $case) {
            $recovered = QuoteStatus::from($case->value);
            $this->assertEquals($case, $recovered);
        }
    }

    public function test_all_invoice_statuses_roundtrip(): void
    {
        foreach (InvoiceStatus::cases() as $case) {
            $recovered = InvoiceStatus::from($case->value);
            $this->assertEquals($case, $recovered);
        }
    }

    public function test_all_expense_statuses_roundtrip(): void
    {
        foreach (ExpenseStatus::cases() as $case) {
            $recovered = ExpenseStatus::from($case->value);
            $this->assertEquals($case, $recovered);
        }
    }

    public function test_all_deal_statuses_roundtrip(): void
    {
        foreach (DealStatus::cases() as $case) {
            $recovered = DealStatus::from($case->value);
            $this->assertEquals($case, $recovered);
        }
    }

    // ═══ EMA edge cases ═══

    public function test_ema_class_i_small_load(): void
    {
        $ema = EmaCalculator::calculate('I', 0.001, 0.05, 'initial');
        $this->assertIsFloat($ema);
    }

    public function test_ema_class_i_i_medium(): void
    {
        $ema = EmaCalculator::calculate('II', 0.01, 50.0, 'initial');
        $this->assertIsFloat($ema);
        $this->assertGreaterThan(0, $ema);
    }

    public function test_ema_class_iii_i_large(): void
    {
        $ema = EmaCalculator::calculate('IIII', 5.0, 50000.0, 'initial');
        $this->assertIsFloat($ema);
        $this->assertGreaterThan(0, $ema);
    }

    public function test_ema_in_use_always_double(): void
    {
        $classes = ['I', 'II', 'III', 'IIII'];
        foreach ($classes as $class) {
            $initial = EmaCalculator::calculate($class, 1.0, 1000.0, 'initial');
            $inUse = EmaCalculator::calculate($class, 1.0, 1000.0, 'in_use');
            if ($initial > 0) {
                $this->assertEquals($initial * 2, $inUse, "Class {$class}: in_use should be 2× initial");
            }
        }
    }

    // ═══ FiscalResult immutability ═══

    public function test_fiscal_result_ok_immutable(): void
    {
        $r = FiscalResult::ok(['provider_id' => 'X', 'number' => '001']);
        $this->assertTrue($r->success);
        $this->assertEquals('X', $r->providerId);
    }

    public function test_fiscal_result_fail_immutable(): void
    {
        $r = FiscalResult::fail('Certificado vencido', ['code' => 999]);
        $this->assertFalse($r->success);
        $this->assertEquals('Certificado vencido', $r->errorMessage);
    }

    // ═══ Cross-enum: WO terminal means no more transitions ═══

    public function test_completed_wo_cannot_go_back(): void
    {
        $allowed = WorkOrderStatus::COMPLETED->allowedTransitions();
        $this->assertNotContains(WorkOrderStatus::OPEN, $allowed);
        $this->assertNotContains(WorkOrderStatus::IN_PROGRESS, $allowed);
    }

    public function test_cancelled_wo_can_reopen(): void
    {
        $allowed = WorkOrderStatus::CANCELLED->allowedTransitions();
        $this->assertContains(WorkOrderStatus::OPEN, $allowed);
    }

    // ═══ Financial status settlement logic ═══

    public function test_paid_is_not_open(): void
    {
        $this->assertFalse(FinancialStatus::PAID->isOpen());
    }

    public function test_pending_is_open(): void
    {
        $this->assertTrue(FinancialStatus::PENDING->isOpen());
    }

    public function test_partial_is_open(): void
    {
        $this->assertTrue(FinancialStatus::PARTIAL->isOpen());
    }

    public function test_overdue_is_open(): void
    {
        $this->assertTrue(FinancialStatus::OVERDUE->isOpen());
    }

    // ═══ Quote mutability ═══

    public function test_draft_quote_is_mutable(): void
    {
        $this->assertTrue(QuoteStatus::DRAFT->isMutable());
    }

    public function test_approved_quote_not_mutable(): void
    {
        $this->assertFalse(QuoteStatus::APPROVED->isMutable());
    }

    public function test_sent_quote_not_mutable(): void
    {
        $this->assertFalse(QuoteStatus::SENT->isMutable());
    }

    public function test_expired_quote_not_mutable(): void
    {
        $this->assertFalse(QuoteStatus::EXPIRED->isMutable());
    }
}
