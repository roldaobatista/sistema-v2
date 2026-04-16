<?php

namespace App\Observers;

use App\Models\TimeClockAuditLog;
use App\Models\TimeClockEntry;
use App\Services\JourneyCalculationService;

class TimeClockEntryObserver
{
    public function created(TimeClockEntry $entry): void
    {
        try {
            TimeClockAuditLog::log('created', $entry->id, null, [
                'clock_in' => $entry->clock_in?->toISOString(),
                'clock_method' => $entry->clock_method,
                'nsr' => $entry->nsr,
                'latitude_in' => $entry->latitude_in,
                'longitude_in' => $entry->longitude_in,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to log clock entry creation', ['error' => $e->getMessage()]);
        }
    }

    public function updated(TimeClockEntry $entry): void
    {
        // Log approval status changes
        if ($entry->wasChanged('approval_status')) {
            $action = match ($entry->approval_status) {
                'approved' => 'approved',
                'rejected' => 'rejected',
                default => null,
            };

            if ($action) {
                try {
                    TimeClockAuditLog::log($action, $entry->id, null, [
                        'approval_status' => $entry->approval_status,
                        'approved_by' => $entry->approved_by,
                        'rejection_reason' => $entry->rejection_reason,
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning('Failed to log approval change', ['error' => $e->getMessage()]);
                }
            }
        }

        // Log clock-out
        if ($entry->wasChanged('clock_out') && $entry->clock_out) {
            try {
                TimeClockAuditLog::log('clock_out', $entry->id, null, [
                    'clock_out' => $entry->clock_out->toISOString(),
                    'latitude_out' => $entry->latitude_out,
                    'longitude_out' => $entry->longitude_out,
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Failed to log clock-out', ['error' => $e->getMessage()]);
            }

            // Auto-calculate journey after clock-out
            if ($entry->approval_status !== 'rejected') {
                try {
                    app(JourneyCalculationService::class)
                        ->calculateDay($entry->user_id, $entry->clock_in->format('Y-m-d'));
                } catch (\Throwable $e) {
                    \Log::error('Failed to auto-calculate journey after clock-out', [
                        'entry_id' => $entry->id,
                        'user_id' => $entry->user_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Log confirmation
        if ($entry->wasChanged('confirmed_at') && $entry->confirmed_at) {
            try {
                TimeClockAuditLog::log('confirmed', $entry->id, null, [
                    'confirmation_method' => $entry->confirmation_method,
                    'confirmation_hash' => $entry->employee_confirmation_hash,
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Failed to log confirmation', ['error' => $e->getMessage()]);
            }
        }
    }
}
