<?php

namespace Tests\Unit\Enums;

use App\Enums\WorkOrderStatus;
use PHPUnit\Framework\TestCase;

/**
 * Testes profundos do WorkOrderStatus enum real:
 * label(), color(), isActive(), isCompleted(), isCancelled(),
 * allowedTransitions(), canTransitionTo()
 */
class WorkOrderStatusEnumTest extends TestCase
{
    // ── label() ──

    public function test_open_label(): void
    {
        $this->assertEquals('Aberta', WorkOrderStatus::OPEN->label());
    }

    public function test_in_service_label(): void
    {
        $this->assertEquals('Em Serviço', WorkOrderStatus::IN_SERVICE->label());
    }

    public function test_completed_label(): void
    {
        $this->assertEquals('Finalizada', WorkOrderStatus::COMPLETED->label());
    }

    public function test_cancelled_label(): void
    {
        $this->assertEquals('Cancelada', WorkOrderStatus::CANCELLED->label());
    }

    public function test_in_displacement_label(): void
    {
        $this->assertEquals('Em Deslocamento', WorkOrderStatus::IN_DISPLACEMENT->label());
    }

    public function test_delivered_label(): void
    {
        $this->assertEquals('Entregue', WorkOrderStatus::DELIVERED->label());
    }

    public function test_invoiced_label(): void
    {
        $this->assertEquals('Faturada', WorkOrderStatus::INVOICED->label());
    }

    public function test_awaiting_return_label(): void
    {
        $this->assertEquals('Serviço Concluído', WorkOrderStatus::AWAITING_RETURN->label());
    }

    public function test_in_progress_label(): void
    {
        $this->assertEquals('Em Andamento', WorkOrderStatus::IN_PROGRESS->label());
    }

    // ── color() ──

    public function test_open_color_info(): void
    {
        $this->assertEquals('info', WorkOrderStatus::OPEN->color());
    }

    public function test_completed_color_success(): void
    {
        $this->assertEquals('success', WorkOrderStatus::COMPLETED->color());
    }

    public function test_cancelled_color_danger(): void
    {
        $this->assertEquals('danger', WorkOrderStatus::CANCELLED->color());
    }

    public function test_in_service_color_warning(): void
    {
        $this->assertEquals('warning', WorkOrderStatus::IN_SERVICE->color());
    }

    public function test_displacement_paused_color_amber(): void
    {
        $this->assertEquals('amber', WorkOrderStatus::DISPLACEMENT_PAUSED->color());
    }

    public function test_in_displacement_color_cyan(): void
    {
        $this->assertEquals('cyan', WorkOrderStatus::IN_DISPLACEMENT->color());
    }

    public function test_invoiced_color_brand(): void
    {
        $this->assertEquals('brand', WorkOrderStatus::INVOICED->color());
    }

    // ── isActive() ──

    public function test_in_displacement_is_active(): void
    {
        $this->assertTrue(WorkOrderStatus::IN_DISPLACEMENT->isActive());
    }

    public function test_at_client_is_active(): void
    {
        $this->assertTrue(WorkOrderStatus::AT_CLIENT->isActive());
    }

    public function test_in_service_is_active(): void
    {
        $this->assertTrue(WorkOrderStatus::IN_SERVICE->isActive());
    }

    public function test_service_paused_is_active(): void
    {
        $this->assertTrue(WorkOrderStatus::SERVICE_PAUSED->isActive());
    }

    public function test_in_return_is_active(): void
    {
        $this->assertTrue(WorkOrderStatus::IN_RETURN->isActive());
    }

    public function test_in_progress_is_active(): void
    {
        $this->assertTrue(WorkOrderStatus::IN_PROGRESS->isActive());
    }

    public function test_open_is_not_active(): void
    {
        $this->assertFalse(WorkOrderStatus::OPEN->isActive());
    }

    public function test_completed_is_not_active(): void
    {
        $this->assertFalse(WorkOrderStatus::COMPLETED->isActive());
    }

    public function test_cancelled_is_not_active(): void
    {
        $this->assertFalse(WorkOrderStatus::CANCELLED->isActive());
    }

    public function test_invoiced_is_not_active(): void
    {
        $this->assertFalse(WorkOrderStatus::INVOICED->isActive());
    }

    // ── isCompleted() ──

    public function test_completed_is_completed(): void
    {
        $this->assertTrue(WorkOrderStatus::COMPLETED->isCompleted());
    }

    public function test_delivered_is_completed(): void
    {
        $this->assertTrue(WorkOrderStatus::DELIVERED->isCompleted());
    }

    public function test_invoiced_is_completed(): void
    {
        $this->assertTrue(WorkOrderStatus::INVOICED->isCompleted());
    }

    public function test_open_is_not_completed(): void
    {
        $this->assertFalse(WorkOrderStatus::OPEN->isCompleted());
    }

    public function test_in_service_is_not_completed(): void
    {
        $this->assertFalse(WorkOrderStatus::IN_SERVICE->isCompleted());
    }

    public function test_cancelled_is_not_completed(): void
    {
        $this->assertFalse(WorkOrderStatus::CANCELLED->isCompleted());
    }

    // ── isCancelled() ──

    public function test_cancelled_is_cancelled(): void
    {
        $this->assertTrue(WorkOrderStatus::CANCELLED->isCancelled());
    }

    public function test_open_is_not_cancelled(): void
    {
        $this->assertFalse(WorkOrderStatus::OPEN->isCancelled());
    }

    public function test_completed_is_not_cancelled(): void
    {
        $this->assertFalse(WorkOrderStatus::COMPLETED->isCancelled());
    }

    // ── allowedTransitions() — comprehensive ──

    public function test_open_allowed_transitions(): void
    {
        $transitions = WorkOrderStatus::OPEN->allowedTransitions();
        $this->assertContains(WorkOrderStatus::AWAITING_DISPATCH, $transitions);
        $this->assertContains(WorkOrderStatus::IN_DISPLACEMENT, $transitions);
        $this->assertContains(WorkOrderStatus::IN_PROGRESS, $transitions);
        $this->assertContains(WorkOrderStatus::WAITING_APPROVAL, $transitions);
        $this->assertContains(WorkOrderStatus::CANCELLED, $transitions);
    }

    public function test_awaiting_dispatch_allowed_transitions(): void
    {
        $transitions = WorkOrderStatus::AWAITING_DISPATCH->allowedTransitions();
        $this->assertContains(WorkOrderStatus::IN_DISPLACEMENT, $transitions);
        $this->assertContains(WorkOrderStatus::CANCELLED, $transitions);
        $this->assertCount(2, $transitions);
    }

    public function test_in_displacement_allowed_transitions(): void
    {
        $transitions = WorkOrderStatus::IN_DISPLACEMENT->allowedTransitions();
        $this->assertContains(WorkOrderStatus::DISPLACEMENT_PAUSED, $transitions);
        $this->assertContains(WorkOrderStatus::AT_CLIENT, $transitions);
        $this->assertContains(WorkOrderStatus::CANCELLED, $transitions);
        $this->assertCount(3, $transitions);
    }

    public function test_displacement_paused_only_resumes(): void
    {
        $transitions = WorkOrderStatus::DISPLACEMENT_PAUSED->allowedTransitions();
        $this->assertEquals([WorkOrderStatus::IN_DISPLACEMENT], $transitions);
    }

    public function test_at_client_transitions(): void
    {
        $transitions = WorkOrderStatus::AT_CLIENT->allowedTransitions();
        $this->assertContains(WorkOrderStatus::IN_SERVICE, $transitions);
        $this->assertContains(WorkOrderStatus::CANCELLED, $transitions);
    }

    public function test_in_service_transitions(): void
    {
        $transitions = WorkOrderStatus::IN_SERVICE->allowedTransitions();
        $this->assertContains(WorkOrderStatus::SERVICE_PAUSED, $transitions);
        $this->assertContains(WorkOrderStatus::WAITING_PARTS, $transitions);
        $this->assertContains(WorkOrderStatus::AWAITING_RETURN, $transitions);
        $this->assertContains(WorkOrderStatus::CANCELLED, $transitions);
    }

    public function test_service_paused_only_resumes(): void
    {
        $transitions = WorkOrderStatus::SERVICE_PAUSED->allowedTransitions();
        $this->assertEquals([WorkOrderStatus::IN_SERVICE], $transitions);
    }

    public function test_invoiced_no_transitions(): void
    {
        $transitions = WorkOrderStatus::INVOICED->allowedTransitions();
        $this->assertEmpty($transitions);
    }

    public function test_cancelled_can_reopen(): void
    {
        $transitions = WorkOrderStatus::CANCELLED->allowedTransitions();
        $this->assertEquals([WorkOrderStatus::OPEN], $transitions);
    }

    // ── canTransitionTo() ──

    public function test_can_transition_to_valid_target(): void
    {
        $this->assertTrue(WorkOrderStatus::OPEN->canTransitionTo(WorkOrderStatus::CANCELLED));
    }

    public function test_cannot_transition_to_invalid_target(): void
    {
        $this->assertFalse(WorkOrderStatus::OPEN->canTransitionTo(WorkOrderStatus::INVOICED));
    }

    public function test_invoiced_cannot_transition_to_anything(): void
    {
        foreach (WorkOrderStatus::cases() as $status) {
            $this->assertFalse(WorkOrderStatus::INVOICED->canTransitionTo($status));
        }
    }

    // ── Enum count ──

    public function test_total_17_cases(): void
    {
        $this->assertCount(17, WorkOrderStatus::cases());
    }

    // ── tryFrom ──

    public function test_try_from_valid(): void
    {
        $this->assertEquals(WorkOrderStatus::OPEN, WorkOrderStatus::tryFrom('open'));
    }

    public function test_try_from_invalid(): void
    {
        $this->assertNull(WorkOrderStatus::tryFrom('nonexistent'));
    }

    // ── Each label is non-empty string ──

    public function test_all_statuses_have_label(): void
    {
        foreach (WorkOrderStatus::cases() as $status) {
            $this->assertNotEmpty($status->label());
        }
    }

    public function test_all_statuses_have_color(): void
    {
        foreach (WorkOrderStatus::cases() as $status) {
            $this->assertNotEmpty($status->color());
        }
    }
}
