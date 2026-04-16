<?php

namespace App\Observers;

use App\Models\TimeClockAdjustment;
use App\Models\TimeClockAuditLog;

class TimeClockAdjustmentObserver
{
    public function created(TimeClockAdjustment $adjustment): void
    {
        try {
            TimeClockAuditLog::log('adjustment_requested', $adjustment->time_clock_entry_id, $adjustment->id, [
                'reason' => $adjustment->reason,
                'original_clock_in' => $adjustment->original_clock_in,
                'adjusted_clock_in' => $adjustment->adjusted_clock_in,
                'original_clock_out' => $adjustment->original_clock_out,
                'adjusted_clock_out' => $adjustment->adjusted_clock_out,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to log adjustment request', ['error' => $e->getMessage()]);
        }
    }

    public function updated(TimeClockAdjustment $adjustment): void
    {
        if ($adjustment->wasChanged('status')) {
            $action = match ($adjustment->status) {
                'approved' => 'adjustment_approved',
                'rejected' => 'adjustment_rejected',
                default => null,
            };

            if ($action) {
                try {
                    TimeClockAuditLog::log($action, $adjustment->time_clock_entry_id, $adjustment->id, [
                        'status' => $adjustment->status,
                        'approved_by' => $adjustment->approved_by,
                        'rejection_reason' => $adjustment->rejection_reason ?? null,
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning('Failed to log adjustment status change', ['error' => $e->getMessage()]);
                }
            }
        }
    }
}
