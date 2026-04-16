<?php

namespace Tests\Unit\Enums;

use App\Enums\FinancialStatus;
use App\Enums\QuoteStatus;
use PHPUnit\Framework\TestCase;

/**
 * Testes profundos dos enums QuoteStatus e FinancialStatus reais.
 */
class QuoteAndFinancialStatusEnumTest extends TestCase
{
    // ═══ QuoteStatus ═══

    // ── label() ──

    public function test_quote_draft_label(): void
    {
        $this->assertEquals('Rascunho', QuoteStatus::DRAFT->label());
    }

    public function test_quote_sent_label(): void
    {
        $this->assertEquals('Enviado', QuoteStatus::SENT->label());
    }

    public function test_quote_approved_label(): void
    {
        $this->assertEquals('Aprovado', QuoteStatus::APPROVED->label());
    }

    public function test_quote_rejected_label(): void
    {
        $this->assertEquals('Rejeitado', QuoteStatus::REJECTED->label());
    }

    public function test_quote_invoiced_label(): void
    {
        $this->assertEquals('Faturado', QuoteStatus::INVOICED->label());
    }

    public function test_quote_in_execution_label(): void
    {
        $this->assertEquals('Em Execução', QuoteStatus::IN_EXECUTION->label());
    }

    public function test_quote_installation_testing_label(): void
    {
        $this->assertEquals('Instalação p/ Teste', QuoteStatus::INSTALLATION_TESTING->label());
    }

    public function test_quote_renegotiation_label(): void
    {
        $this->assertEquals('Em Renegociação', QuoteStatus::RENEGOTIATION->label());
    }

    public function test_all_quote_statuses_have_labels(): void
    {
        foreach (QuoteStatus::cases() as $status) {
            $this->assertNotEmpty($status->label());
        }
    }

    // ── color() ──

    public function test_all_quote_statuses_have_colors(): void
    {
        foreach (QuoteStatus::cases() as $status) {
            $this->assertNotEmpty($status->color());
        }
    }

    public function test_quote_draft_color(): void
    {
        $this->assertStringContainsString('surface', QuoteStatus::DRAFT->color());
    }

    public function test_quote_approved_color(): void
    {
        $this->assertStringContainsString('emerald', QuoteStatus::APPROVED->color());
    }

    public function test_quote_rejected_color(): void
    {
        $this->assertStringContainsString('red', QuoteStatus::REJECTED->color());
    }

    // ── isMutable() ──

    public function test_draft_is_mutable(): void
    {
        $this->assertTrue(QuoteStatus::DRAFT->isMutable());
    }

    public function test_pending_internal_is_mutable(): void
    {
        $this->assertTrue(QuoteStatus::PENDING_INTERNAL_APPROVAL->isMutable());
    }

    public function test_rejected_is_mutable(): void
    {
        $this->assertTrue(QuoteStatus::REJECTED->isMutable());
    }

    public function test_renegotiation_is_mutable(): void
    {
        $this->assertTrue(QuoteStatus::RENEGOTIATION->isMutable());
    }

    public function test_sent_is_not_mutable(): void
    {
        $this->assertFalse(QuoteStatus::SENT->isMutable());
    }

    public function test_approved_is_not_mutable(): void
    {
        $this->assertFalse(QuoteStatus::APPROVED->isMutable());
    }

    public function test_invoiced_is_not_mutable(): void
    {
        $this->assertFalse(QuoteStatus::INVOICED->isMutable());
    }

    // ── isConvertible() ──

    public function test_approved_is_convertible(): void
    {
        $this->assertTrue(QuoteStatus::APPROVED->isConvertible());
    }

    public function test_internally_approved_is_convertible(): void
    {
        $this->assertTrue(QuoteStatus::INTERNALLY_APPROVED->isConvertible());
    }

    public function test_draft_is_not_convertible(): void
    {
        $this->assertFalse(QuoteStatus::DRAFT->isConvertible());
    }

    public function test_sent_is_not_convertible(): void
    {
        $this->assertFalse(QuoteStatus::SENT->isConvertible());
    }

    public function test_rejected_is_not_convertible(): void
    {
        $this->assertFalse(QuoteStatus::REJECTED->isConvertible());
    }

    public function test_invoiced_is_not_convertible(): void
    {
        $this->assertFalse(QuoteStatus::INVOICED->isConvertible());
    }

    // ── count ──

    public function test_quote_status_has_11_cases(): void
    {
        $this->assertCount(11, QuoteStatus::cases());
    }

    // ── tryFrom ──

    public function test_quote_try_from_valid(): void
    {
        $this->assertEquals(QuoteStatus::DRAFT, QuoteStatus::tryFrom('draft'));
    }

    public function test_quote_try_from_invalid(): void
    {
        $this->assertNull(QuoteStatus::tryFrom('nonexistent'));
    }

    // ═══ FinancialStatus ═══

    // ── label() ──

    public function test_financial_pending_label(): void
    {
        $this->assertEquals('Pendente', FinancialStatus::PENDING->label());
    }

    public function test_financial_partial_label(): void
    {
        $this->assertEquals('Parcial', FinancialStatus::PARTIAL->label());
    }

    public function test_financial_paid_label(): void
    {
        $this->assertEquals('Pago', FinancialStatus::PAID->label());
    }

    public function test_financial_overdue_label(): void
    {
        $this->assertEquals('Vencido', FinancialStatus::OVERDUE->label());
    }

    public function test_financial_cancelled_label(): void
    {
        $this->assertEquals('Cancelado', FinancialStatus::CANCELLED->label());
    }

    public function test_financial_renegotiated_label(): void
    {
        $this->assertEquals('Renegociado', FinancialStatus::RENEGOTIATED->label());
    }

    // ── color() ──

    public function test_financial_pending_color(): void
    {
        $this->assertEquals('warning', FinancialStatus::PENDING->color());
    }

    public function test_financial_paid_color(): void
    {
        $this->assertEquals('success', FinancialStatus::PAID->color());
    }

    public function test_financial_overdue_color(): void
    {
        $this->assertEquals('danger', FinancialStatus::OVERDUE->color());
    }

    // ── isOpen() ──

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

    public function test_paid_is_not_open(): void
    {
        $this->assertFalse(FinancialStatus::PAID->isOpen());
    }

    public function test_cancelled_is_not_open(): void
    {
        $this->assertFalse(FinancialStatus::CANCELLED->isOpen());
    }

    // ── isSettled() ──

    public function test_paid_is_settled(): void
    {
        $this->assertTrue(FinancialStatus::PAID->isSettled());
    }

    public function test_cancelled_is_settled(): void
    {
        $this->assertTrue(FinancialStatus::CANCELLED->isSettled());
    }

    public function test_renegotiated_is_settled(): void
    {
        $this->assertTrue(FinancialStatus::RENEGOTIATED->isSettled());
    }

    public function test_pending_is_not_settled(): void
    {
        $this->assertFalse(FinancialStatus::PENDING->isSettled());
    }

    public function test_overdue_is_not_settled(): void
    {
        $this->assertFalse(FinancialStatus::OVERDUE->isSettled());
    }

    // ── count ──

    public function test_financial_status_has_7_cases(): void
    {
        $this->assertCount(7, FinancialStatus::cases());
    }
}
