<?php

namespace App\Services\Journey;

use App\Models\JourneyApproval;
use App\Models\JourneyEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DualApprovalService
{
    public function submitForApproval(JourneyEntry $journeyDay): JourneyEntry
    {
        return DB::transaction(function () use ($journeyDay) {
            // Create operational approval if not exists
            JourneyApproval::withoutGlobalScope('tenant')->firstOrCreate(
                [
                    'tenant_id' => $journeyDay->tenant_id,
                    'journey_entry_id' => $journeyDay->id,
                    'level' => 'operational',
                ],
                ['status' => 'pending'],
            );

            // Create HR approval if not exists
            JourneyApproval::withoutGlobalScope('tenant')->firstOrCreate(
                [
                    'tenant_id' => $journeyDay->tenant_id,
                    'journey_entry_id' => $journeyDay->id,
                    'level' => 'hr',
                ],
                ['status' => 'pending'],
            );

            $journeyDay->update([
                'operational_approval_status' => 'pending',
                'hr_approval_status' => 'pending',
            ]);

            return $journeyDay->fresh();
        });
    }

    public function approveOperational(JourneyEntry $journeyDay, User $approver, ?string $notes = null): JourneyEntry
    {
        return DB::transaction(function () use ($journeyDay, $approver, $notes) {
            $approval = JourneyApproval::withoutGlobalScope('tenant')
                ->where('journey_entry_id', $journeyDay->id)
                ->where('level', 'operational')
                ->firstOrFail();

            $approval->update([
                'status' => 'approved',
                'approver_id' => $approver->id,
                'decided_at' => now(),
                'notes' => $notes,
            ]);

            $journeyDay->update([
                'operational_approval_status' => 'approved',
                'operational_approver_id' => $approver->id,
                'operational_approved_at' => now(),
            ]);

            return $journeyDay->fresh();
        });
    }

    public function rejectOperational(JourneyEntry $journeyDay, User $approver, string $reason): JourneyEntry
    {
        return DB::transaction(function () use ($journeyDay, $approver, $reason) {
            $approval = JourneyApproval::withoutGlobalScope('tenant')
                ->where('journey_entry_id', $journeyDay->id)
                ->where('level', 'operational')
                ->firstOrFail();

            $approval->update([
                'status' => 'rejected',
                'approver_id' => $approver->id,
                'decided_at' => now(),
                'notes' => $reason,
            ]);

            $journeyDay->update([
                'operational_approval_status' => 'rejected',
                'operational_approver_id' => $approver->id,
                'operational_approved_at' => now(),
            ]);

            return $journeyDay->fresh();
        });
    }

    public function approveHr(JourneyEntry $journeyDay, User $approver, ?string $notes = null): JourneyEntry
    {
        if ($journeyDay->operational_approval_status !== 'approved') {
            throw new \DomainException('Aprovação operacional deve ser feita antes da aprovação RH.');
        }

        return DB::transaction(function () use ($journeyDay, $approver, $notes) {
            $approval = JourneyApproval::withoutGlobalScope('tenant')
                ->where('journey_entry_id', $journeyDay->id)
                ->where('level', 'hr')
                ->firstOrFail();

            $approval->update([
                'status' => 'approved',
                'approver_id' => $approver->id,
                'decided_at' => now(),
                'notes' => $notes,
            ]);

            $journeyDay->update([
                'hr_approval_status' => 'approved',
                'hr_approver_id' => $approver->id,
                'hr_approved_at' => now(),
                'is_closed' => true,
            ]);

            return $journeyDay->fresh();
        });
    }

    public function rejectHr(JourneyEntry $journeyDay, User $approver, string $reason): JourneyEntry
    {
        return DB::transaction(function () use ($journeyDay, $approver, $reason) {
            $approval = JourneyApproval::withoutGlobalScope('tenant')
                ->where('journey_entry_id', $journeyDay->id)
                ->where('level', 'hr')
                ->firstOrFail();

            $approval->update([
                'status' => 'rejected',
                'approver_id' => $approver->id,
                'decided_at' => now(),
                'notes' => $reason,
            ]);

            // Reopen operational for re-adjustment
            $operationalApproval = JourneyApproval::withoutGlobalScope('tenant')
                ->where('journey_entry_id', $journeyDay->id)
                ->where('level', 'operational')
                ->first();

            if ($operationalApproval) {
                $operationalApproval->update([
                    'status' => 'pending',
                    'approver_id' => null,
                    'decided_at' => null,
                    'notes' => null,
                ]);
            }

            $journeyDay->update([
                'hr_approval_status' => 'rejected',
                'hr_approver_id' => $approver->id,
                'hr_approved_at' => now(),
                'operational_approval_status' => 'pending',
                'operational_approver_id' => null,
                'operational_approved_at' => null,
                'is_closed' => false,
            ]);

            return $journeyDay->fresh();
        });
    }

    public function getPendingApprovals(int $tenantId, string $level, int $perPage = 25): mixed
    {
        return JourneyEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where($level === 'operational' ? 'operational_approval_status' : 'hr_approval_status', 'pending')
            ->with(['user:id,name', 'blocks'])
            ->orderBy('reference_date')
            ->paginate($perPage);
    }
}
