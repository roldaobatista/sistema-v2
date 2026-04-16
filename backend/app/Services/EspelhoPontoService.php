<?php

namespace App\Services;

use App\Models\HourBankTransaction;
use App\Models\JourneyEntry;
use App\Models\TimeClockEntry;
use App\Models\User;
use Carbon\Carbon;

class EspelhoPontoService
{
    public function generate(int $userId, int $year, int $month): array
    {
        $user = User::with(['journeyRule'])->findOrFail($userId);
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $clockEntries = TimeClockEntry::where('user_id', $userId)
            ->whereBetween('clock_in', [$startDate, $endDate])
            ->orderBy('clock_in')
            ->get();

        $journeyEntries = JourneyEntry::where('user_id', $userId)
            ->whereBetween('reference_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('reference_date')
            ->get();

        $hourBankBefore = HourBankTransaction::where('user_id', $userId)
            ->where('reference_date', '<', $startDate->format('Y-m-d'))
            ->orderBy('id', 'desc')
            ->value('balance_after') ?? 0;

        $hourBankAfter = HourBankTransaction::where('user_id', $userId)
            ->where('reference_date', '<=', $endDate->format('Y-m-d'))
            ->orderBy('id', 'desc')
            ->value('balance_after') ?? $hourBankBefore;

        $totals = [
            'worked_hours' => round($journeyEntries->sum('worked_hours'), 2),
            'overtime_50' => round($journeyEntries->sum('overtime_hours_50'), 2),
            'overtime_100' => round($journeyEntries->sum('overtime_hours_100'), 2),
            'night_hours' => round($journeyEntries->sum('night_hours'), 2),
            'absence_hours' => round($journeyEntries->sum('absence_hours'), 2),
            'hour_bank_previous' => round($hourBankBefore, 2),
            'hour_bank_current' => round($hourBankAfter, 2),
        ];

        $days = [];
        foreach ($journeyEntries as $je) {
            $dayClocks = $clockEntries->filter(function ($c) use ($je) {
                return $c->clock_in && $c->clock_in->format('Y-m-d') === $je->reference_date->format('Y-m-d');
            });

            $days[] = [
                'date' => $je->reference_date->format('Y-m-d'),
                'day_of_week' => $je->reference_date->dayOfWeek,
                'is_holiday' => (bool) $je->is_holiday,
                'is_dsr' => (bool) $je->is_dsr,
                'status' => $je->status,
                'clock_entries' => $dayClocks->map(fn ($c) => [
                    'clock_in' => $c->clock_in?->format('H:i:s'),
                    'clock_out' => $c->clock_out?->format('H:i:s'),
                    'break_start' => $c->break_start?->format('H:i:s'),
                    'break_end' => $c->break_end?->format('H:i:s'),
                    'location_in' => ['lat' => $c->latitude_in, 'lng' => $c->longitude_in],
                    'location_out' => ['lat' => $c->latitude_out, 'lng' => $c->longitude_out],
                    'approval_status' => $c->approval_status,
                    'nsr' => $c->nsr,
                    'confirmed_at' => $c->confirmed_at?->toISOString(),
                ])->values()->toArray(),
                'scheduled_hours' => (float) $je->scheduled_hours,
                'worked_hours' => (float) $je->worked_hours,
                'overtime_50' => (float) $je->overtime_hours_50,
                'overtime_100' => (float) $je->overtime_hours_100,
                'night_hours' => (float) $je->night_hours,
                'absence_hours' => (float) $je->absence_hours,
                'break_compliance' => $je->break_compliance,
                'inter_shift_hours' => $je->inter_shift_hours,
                'hour_bank_balance' => (float) $je->hour_bank_balance,
            ];
        }

        return [
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'pis' => $user->pis,
                'cpf' => $user->cpf,
                'position' => $user->position ?? null,
                'department' => $user->department ?? null,
                'admission_date' => $user->admission_date,
            ],
            'period' => ['year' => $year, 'month' => $month],
            'journey_rule' => $user->journeyRule ? [
                'name' => $user->journeyRule->name,
                'daily_hours' => (float) $user->journeyRule->daily_hours,
                'weekly_hours' => (float) $user->journeyRule->weekly_hours,
            ] : null,
            'days' => $days,
            'totals' => $totals,
            'generated_at' => now()->toISOString(),
            'integrity_hash' => hash('sha256', json_encode($days)),
        ];
    }
}
