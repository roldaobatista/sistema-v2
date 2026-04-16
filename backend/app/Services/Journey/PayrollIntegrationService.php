<?php

namespace App\Services\Journey;

use App\Models\JourneyEntry;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PayrollIntegrationService
{
    /**
     * Export journey hours summary for a given month, ready for payroll.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function exportMonthSummary(int $tenantId, string $yearMonth): Collection
    {
        [$year, $month] = explode('-', $yearMonth);
        $startDate = Carbon::create((int) $year, (int) $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $closedDays = JourneyEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_closed', true)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('user:id,name')
            ->get()
            ->groupBy('user_id');

        $summary = [];
        foreach ($closedDays as $userId => $days) {
            /** @var Collection<int, JourneyEntry> $days */
            $firstDay = $days->first();
            $user = $firstDay?->user;
            $userName = $user instanceof User ? $user->name : 'N/A';

            $summary[] = [
                'user_id' => $userId,
                'user_name' => $userName,
                'year_month' => $yearMonth,
                'working_days' => $days->count(),
                'total_worked_hours' => round($days->sum('total_minutes_worked') / 60, 2),
                'total_overtime_hours' => round($days->sum('total_minutes_overtime') / 60, 2),
                'total_travel_hours' => round($days->sum('total_minutes_travel') / 60, 2),
                'total_break_hours' => round($days->sum('total_minutes_break') / 60, 2),
                'total_oncall_hours' => round($days->sum('total_minutes_oncall') / 60, 2),
                'total_overnight_hours' => round($days->sum('total_minutes_overnight') / 60, 2),
                'all_days_closed' => true,
                'all_days_approved' => $days->every(fn (JourneyEntry $day): bool => $day->isFullyApproved()),
            ];
        }

        /** @var Collection<int, array<string, mixed>> $result */
        $result = collect($summary);

        return $result;
    }

    /**
     * Get unclosed days that block payroll for a month.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getBlockingDays(int $tenantId, string $yearMonth): Collection
    {
        [$year, $month] = explode('-', $yearMonth);
        $startDate = Carbon::create((int) $year, (int) $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $days = JourneyEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_closed', false)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('user:id,name')
            ->get();

        $blockingDays = [];
        foreach ($days as $day) {
            $user = $day->user;
            $blockingDays[] = [
                'journey_day_id' => $day->id,
                'user_id' => $day->user_id,
                'user_name' => $user instanceof User ? $user->name : null,
                'date' => $day->date->format('Y-m-d'),
                'operational_status' => $day->operational_approval_status,
                'hr_status' => $day->hr_approval_status,
            ];
        }

        /** @var Collection<int, array<string, mixed>> $result */
        $result = collect($blockingDays);

        return $result;
    }
}
