<?php

namespace Tests\Unit;

use App\Enums\CommissionSettlementStatus;
use PHPUnit\Framework\TestCase;

class CommissionSettlementStatusTest extends TestCase
{
    public function test_pending_approval_is_canonicalized_as_closed(): void
    {
        $this->assertSame(
            CommissionSettlementStatus::CLOSED->value,
            CommissionSettlementStatus::canonicalValue(CommissionSettlementStatus::PENDING_APPROVAL)
        );
    }

    public function test_closed_filter_includes_legacy_pending_approval(): void
    {
        $this->assertSame(
            [
                CommissionSettlementStatus::CLOSED->value,
                CommissionSettlementStatus::PENDING_APPROVAL->value,
            ],
            CommissionSettlementStatus::normalizeFilter(CommissionSettlementStatus::CLOSED->value)
        );
    }

    public function test_pending_approval_label_is_marked_as_legacy(): void
    {
        $this->assertSame(
            'Aguard. Aprovação (legado)',
            CommissionSettlementStatus::PENDING_APPROVAL->label()
        );
    }
}
