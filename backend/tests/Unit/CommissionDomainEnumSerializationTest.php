<?php

namespace Tests\Unit;

use App\Enums\CommissionDisputeStatus;
use App\Enums\CommissionEventStatus;
use App\Enums\CommissionSettlementStatus;
use App\Enums\RecurringCommissionStatus;
use App\Models\CommissionDispute;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\RecurringCommission;
use Tests\TestCase;

class CommissionDomainEnumSerializationTest extends TestCase
{
    public function test_domain_models_serialize_backed_enums_as_strings(): void
    {
        $event = new CommissionEvent;
        $event->status = CommissionEventStatus::APPROVED;

        $settlement = new CommissionSettlement;
        $settlement->status = CommissionSettlementStatus::PENDING_APPROVAL;

        $dispute = new CommissionDispute;
        $dispute->status = CommissionDisputeStatus::RESOLVED;

        $recurring = new RecurringCommission;
        $recurring->status = RecurringCommissionStatus::ACTIVE;

        $this->assertSame('approved', $event->toArray()['status']);
        $this->assertSame('pending_approval', $settlement->toArray()['status']);
        $this->assertSame('resolved', $dispute->toArray()['status']);
        $this->assertSame('active', $recurring->toArray()['status']);
    }

    public function test_domain_helpers_deserialize_legacy_values_to_canonical_or_compatible_filters(): void
    {
        $this->assertSame(
            ['closed', 'pending_approval'],
            CommissionSettlementStatus::normalizeFilter('pending_approval')
        );

        $this->assertSame(
            ['accepted', 'rejected', 'resolved'],
            CommissionDisputeStatus::normalizeFilter('resolved')
        );

        $this->assertSame('tecnico', CommissionRule::normalizeRole('technician'));
        $this->assertSame('vendedor', CommissionRule::normalizeRole('seller'));
        $this->assertSame('motorista', CommissionRule::normalizeRole('driver'));
    }
}
