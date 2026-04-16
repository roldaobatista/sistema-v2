<?php

namespace App\Observers;

use App\Enums\WorkOrderStatus;
use App\Models\CommissionRule;
use App\Models\SlaPolicy;
use App\Models\TimeClockEntry;
use App\Models\WorkOrder;
use App\Services\CommissionService;
use App\Services\HolidayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WorkOrderObserver
{
    public function creating(WorkOrder $workOrder): void
    {
        try {
            if ($workOrder->sla_policy_id) {
                $this->applySlaPolicy($workOrder);
            }
        } catch (\Throwable $e) {
            Log::warning('WorkOrderObserver: SLA application failed during creating', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updating(WorkOrder $workOrder): void
    {
        $slaFieldChanged = $workOrder->isDirty('sla_policy_id') || $workOrder->isDirty('priority');
        if ($slaFieldChanged && $workOrder->sla_policy_id) {
            try {
                $this->applySlaPolicy($workOrder);
            } catch (\Throwable $e) {
                Log::warning('WorkOrderObserver: SLA application failed during updating', [
                    'work_order_id' => $workOrder->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Auto-track SLA first response time
        if ($workOrder->isDirty('status') &&
            $workOrder->status !== WorkOrder::STATUS_OPEN &&
            ! $workOrder->sla_responded_at) {
            $workOrder->sla_responded_at = now();
        }

        // Validate status transitions at model level
        if ($workOrder->isDirty('status')) {
            $oldStatus = $workOrder->getOriginal('status');
            $newStatus = $workOrder->status;

            $oldEnum = WorkOrderStatus::tryFrom($oldStatus);
            $newEnum = WorkOrderStatus::tryFrom($newStatus);

            if ($oldEnum && $newEnum && ! $oldEnum->canTransitionTo($newEnum)) {
                Log::warning("WorkOrderObserver: Invalid transition {$oldStatus} → {$newStatus}", [
                    'work_order_id' => $workOrder->id,
                ]);

                throw ValidationException::withMessages([
                    'status' => "Transição de status inválida para OS #{$workOrder->id}: {$oldStatus} → {$newStatus}.",
                ]);
            }
        }

        // Audit log for critical field changes
        $criticalFields = ['status', 'total', 'assigned_to', 'priority'];
        $changes = [];
        foreach ($criticalFields as $field) {
            if ($workOrder->isDirty($field)) {
                $changes[$field] = [
                    'from' => $workOrder->getOriginal($field),
                    'to' => $workOrder->getAttribute($field),
                ];
            }
        }
        if (! empty($changes)) {
            try {
                Log::channel('audit')->info("WorkOrder #{$workOrder->id} updated", $changes);
            } catch (\Throwable) {
                // Fallback: log to default channel if 'audit' is unavailable
                Log::info("WorkOrder #{$workOrder->id} updated", $changes);
            }
        }
    }

    public function updated(WorkOrder $workOrder): void
    {
        // Auto-set timestamps based on status changes
        if ($workOrder->wasChanged('status')) {
            try {
                $timestamps = [];

                if ($workOrder->status === WorkOrder::STATUS_COMPLETED && ! $workOrder->completed_at) {
                    $timestamps['completed_at'] = now();
                }
                if ($workOrder->status === WorkOrder::STATUS_CANCELLED && ! $workOrder->cancelled_at) {
                    $timestamps['cancelled_at'] = now();
                }

                if (! empty($timestamps)) {
                    $workOrder->updateQuietly($timestamps);
                }

                // Auto clock-in when technician starts service
                $this->handleAutoClockFromOS($workOrder);

                // Auto-generate commissions based on status trigger
                $this->handleAutoCommission($workOrder);
            } catch (\Throwable $e) {
                Log::warning("WorkOrderObserver: failed to process status change for WO #{$workOrder->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Auto clock-in/out based on work order status transitions.
     */
    protected function handleAutoClockFromOS(WorkOrder $workOrder): void
    {
        $technicianId = $workOrder->assigned_to ?? $workOrder->technician_id ?? null;
        if (! $technicianId) {
            return;
        }

        $newStatus = $workOrder->status;

        // Auto clock-in when starting service
        if ($newStatus === WorkOrder::STATUS_IN_SERVICE) {
            $hasOpenClock = TimeClockEntry::where('user_id', $technicianId)
                ->whereNull('clock_out')
                ->whereDate('clock_in', today())
                ->exists();

            if (! $hasOpenClock) {
                try {
                    TimeClockEntry::create([
                        'tenant_id' => $workOrder->tenant_id,
                        'user_id' => $technicianId,
                        'work_order_id' => $workOrder->id,
                        'clock_in' => now(),
                        'clock_method' => 'auto_os',
                        'approval_status' => 'auto_approved',
                        'notes' => "Clock-in automático - Início OS #{$workOrder->id}",
                    ]);

                    Log::info("Auto clock-in created for user {$technicianId} from WO #{$workOrder->id}");
                } catch (\Throwable $e) {
                    Log::warning("WorkOrderObserver: auto clock-in failed for WO #{$workOrder->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Auto clock-out when work order is completed
        if ($newStatus === WorkOrder::STATUS_COMPLETED) {
            $openEntry = TimeClockEntry::where('user_id', $technicianId)
                ->where('work_order_id', $workOrder->id)
                ->where('clock_method', 'auto_os')
                ->whereNull('clock_out')
                ->latest('clock_in')
                ->first();

            if ($openEntry) {
                try {
                    $openEntry->updateQuietly([
                        'clock_out' => now(),
                        'notes' => $openEntry->notes." | Clock-out automático - OS #{$workOrder->id} concluída",
                    ]);

                    Log::info("Auto clock-out for user {$technicianId} from WO #{$workOrder->id}");
                } catch (\Throwable $e) {
                    Log::warning("WorkOrderObserver: auto clock-out failed for WO #{$workOrder->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Auto-generate commission events when WO reaches a trigger status.
     */
    protected function handleAutoCommission(WorkOrder $workOrder): void
    {
        $triggerMap = [
            WorkOrder::STATUS_COMPLETED => CommissionRule::WHEN_OS_COMPLETED,
            WorkOrder::STATUS_INVOICED => CommissionRule::WHEN_OS_INVOICED,
        ];

        $trigger = $triggerMap[$workOrder->status] ?? null;
        if (! $trigger) {
            return;
        }

        try {
            $service = app(CommissionService::class);
            $events = $service->calculateAndGenerate($workOrder, $trigger);

            if (! empty($events)) {
                Log::info('WorkOrderObserver: Auto-generated '.count($events)." commission event(s) for WO #{$workOrder->id} [trigger:{$trigger}]");
            }
        } catch (\Throwable $e) {
            // Commission generation failure must never block WO status change
            Log::warning("WorkOrderObserver: Commission auto-generation failed for WO #{$workOrder->id}", [
                'trigger' => $trigger,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function applySlaPolicy(WorkOrder $workOrder): void
    {
        $policy = SlaPolicy::where('id', $workOrder->sla_policy_id)
            ->where('tenant_id', $workOrder->tenant_id)
            ->first();

        if (! $policy) {
            return;
        }

        $minutes = $policy->resolution_time_minutes;

        if ($workOrder->priority === WorkOrder::PRIORITY_URGENT) {
            $minutes = (int) ($minutes * 0.5);
        } elseif ($workOrder->priority === WorkOrder::PRIORITY_HIGH) {
            $minutes = (int) ($minutes * 0.8);
        }

        try {
            $holidayService = app(HolidayService::class);
            $workOrder->sla_due_at = $holidayService->addBusinessMinutes(Carbon::now(), $minutes);
        } catch (\Throwable $e) {
            // Fallback: usar cálculo simples sem considerar feriados
            $workOrder->sla_due_at = Carbon::now()->addMinutes($minutes);
            Log::warning('WorkOrderObserver: HolidayService falhou, usando cálculo simples', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
