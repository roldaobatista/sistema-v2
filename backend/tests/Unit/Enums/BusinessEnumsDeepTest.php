<?php

namespace Tests\Unit\Enums;

use App\Enums\ExpenseStatus;
use App\Enums\WorkOrderStatus;
use PHPUnit\Framework\TestCase;

/**
 * Testes de Enums business-critical: ExpenseStatus,
 * WorkOrderStatus behavior rules.
 */
class BusinessEnumsDeepTest extends TestCase
{
    // ═══ ExpenseStatus ═══

    public function test_expense_status_pending(): void
    {
        $this->assertEquals('pending', ExpenseStatus::PENDING->value);
    }

    public function test_expense_status_approved(): void
    {
        $this->assertEquals('approved', ExpenseStatus::APPROVED->value);
    }

    public function test_expense_status_rejected(): void
    {
        $this->assertEquals('rejected', ExpenseStatus::REJECTED->value);
    }

    public function test_expense_status_labels(): void
    {
        foreach (ExpenseStatus::cases() as $case) {
            $this->assertNotEmpty($case->label());
        }
    }

    public function test_expense_status_colors(): void
    {
        foreach (ExpenseStatus::cases() as $case) {
            $this->assertNotEmpty($case->color());
        }
    }

    // ═══ WorkOrderStatus — deep transition logic ═══

    public function test_wo_status_all_cases_have_label(): void
    {
        foreach (WorkOrderStatus::cases() as $case) {
            $this->assertNotEmpty($case->label());
        }
    }

    public function test_wo_status_all_cases_have_color(): void
    {
        foreach (WorkOrderStatus::cases() as $case) {
            $this->assertNotEmpty($case->color());
        }
    }

    public function test_wo_open_is_not_completed(): void
    {
        $this->assertFalse(WorkOrderStatus::OPEN->isCompleted());
    }

    public function test_wo_completed_is_completed(): void
    {
        $this->assertTrue(WorkOrderStatus::COMPLETED->isCompleted());
    }

    public function test_wo_in_progress_is_active(): void
    {
        $this->assertTrue(WorkOrderStatus::IN_PROGRESS->isActive());
    }

    public function test_wo_cancelled_is_not_active(): void
    {
        $this->assertFalse(WorkOrderStatus::CANCELLED->isActive());
    }

    public function test_wo_can_transition_open_to_in_progress(): void
    {
        $allowed = WorkOrderStatus::OPEN->allowedTransitions();
        $this->assertContains(WorkOrderStatus::IN_PROGRESS, $allowed);
    }

    public function test_wo_invoiced_has_no_transitions(): void
    {
        $allowed = WorkOrderStatus::INVOICED->allowedTransitions();
        $this->assertEmpty($allowed);
    }

    public function test_wo_cancelled_can_reopen(): void
    {
        $allowed = WorkOrderStatus::CANCELLED->allowedTransitions();
        $this->assertContains(WorkOrderStatus::OPEN, $allowed);
    }

    public function test_wo_try_from_valid(): void
    {
        $case = WorkOrderStatus::tryFrom('open');
        $this->assertEquals(WorkOrderStatus::OPEN, $case);
    }

    public function test_wo_try_from_invalid(): void
    {
        $case = WorkOrderStatus::tryFrom('nonexistent');
        $this->assertNull($case);
    }

    // ═══ Cross-enum validation ═══

    public function test_all_enums_have_string_values(): void
    {
        $enums = [ExpenseStatus::cases(), WorkOrderStatus::cases()];
        foreach ($enums as $cases) {
            foreach ($cases as $case) {
                $this->assertIsString($case->value);
                $this->assertNotEmpty($case->value);
            }
        }
    }
}
