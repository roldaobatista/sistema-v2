<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\JourneyEntry;
use App\Models\JourneyRule;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Support\Decimal;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

class JourneyCalculationService
{
    /**
     * Calculate journey for a single user on a single day.
     */
    public function calculateDay(int $userId, string $date, int $tenantId): JourneyEntry
    {
        $dateObj = Carbon::parse($date);
        $rule = $this->getRuleForUser($userId, $tenantId);

        $tenant = Tenant::find($tenantId);
        $tz = $tenant->timezone ?? 'America/Sao_Paulo';

        $localDayStart = Carbon::parse($date, $tz)->startOfDay()->utc();
        $localDayEnd = Carbon::parse($date, $tz)->endOfDay()->utc();

        // Get all approved clock entries for this day
        $entries = TimeClockEntry::where('user_id', $userId)
            ->whereBetween('clock_in', [$localDayStart, $localDayEnd])
            ->whereNotNull('clock_out')
            ->where(function ($q) {
                $q->where('approval_status', 'auto_approved')
                    ->orWhere('approval_status', 'approved');
            })
            ->orderBy('clock_in')
            ->get();

        $workedMinutes = 0;
        $nightMinutes = 0;

        foreach ($entries as $entry) {
            $localIn = $entry->clock_in->copy()->setTimezone($tz);
            $localOut = $entry->clock_out->copy()->setTimezone($tz);

            $workedMinutes += $localIn->diffInMinutes($localOut);
            $nightMinutes += $this->calculateNightMinutes(
                $localIn,
                $localOut,
                $rule->night_start,
                $rule->night_end
            );
        }

        // Art. 73 §1 CLT: Hora noturna reduzida — 52min30s reais = 1h paga
        // Bonus de minutos noturnos adicionado às horas trabalhadas
        $nightBonusMinutes = $nightMinutes > 0 ? round($nightMinutes * (60 / config('hr.clt.night_hour_minutes') - 1), 0) : 0;
        $workedMinutes += $nightBonusMinutes;

        $workedHours = round((float) bcdiv((string) $workedMinutes, '60', 4), 2);
        $nightHourMin = (string) config('hr.clt.night_hour_minutes');
        $nightHours = round((float) bcdiv((string) $nightMinutes, $nightHourMin, 4), 2); // Hora noturna reduzida
        $scheduledHours = (float) $rule->daily_hours;

        // Art. 58 §1 CLT: Tolerância de 5min por marcação, máximo 10min/dia
        // Variações de até 5min na entrada e 5min na saída (total ≤10min) não são
        // descontadas nem computadas como hora extra.
        $toleranceApplied = false;
        $scheduledMinutes = $scheduledHours * 60;
        if ($scheduledMinutes > 0 && $workedMinutes > 0 && $entries->isNotEmpty()) {
            $totalVariation = abs($workedMinutes - $scheduledMinutes);
            if ($totalVariation <= config('hr.clt.tolerance_daily_max_minutes')) {
                // With single entry: total variation ≤ 10min covers both punches
                // With multiple entries: ensure no single entry deviates excessively
                $withinTolerance = true;
                if ($entries->count() > 1) {
                    $entryCount = (string) $entries->count();
                    $scheduledPerEntryStr = bcdiv((string) $scheduledMinutes, $entryCount, 4);
                    $scheduledPerEntry = (float) $scheduledPerEntryStr;
                    foreach ($entries as $entry) {
                        $entryMinutes = $entry->clock_in->diffInMinutes($entry->clock_out);
                        if (abs($entryMinutes - $scheduledPerEntry) > config('hr.clt.tolerance_daily_max_minutes')) {
                            $withinTolerance = false;
                            break;
                        }
                    }
                }
                if ($withinTolerance) {
                    $toleranceApplied = true;
                    $workedMinutes = (float) $scheduledMinutes;
                    $workedHours = round((float) bcdiv((string) $workedMinutes, '60', 4), 2);
                }
            }
        }

        $isHoliday = Holiday::isHoliday($tenantId, $date);
        $isSunday = $dateObj->isSunday();
        $isSaturday = $dateObj->isSaturday();
        $isDsr = $isSunday; // DSR = rest day (typically Sunday)

        // Calculate overtime
        $overtimeHours50 = 0;
        $overtimeHours100 = 0;
        $absenceHours = 0;

        if ($isHoliday || $isDsr) {
            // All hours worked on holiday/DSR are 100%
            $overtimeHours100 = $workedHours;
        } elseif ($isSaturday && $scheduledHours <= 4) {
            // Saturday with half-day: normal up to 4h, rest is 50%
            if ($workedHours > 4) {
                $overtimeHours50 = bcsub((string) $workedHours, '4', 2);
            }
        } else {
            // Regular day
            if ($workedHours > $scheduledHours) {
                $overtimeHours50 = bcsub((string) $workedHours, (string) $scheduledHours, 2);
            } elseif ($workedHours < $scheduledHours && $workedHours > 0) {
                $absenceHours = bcsub((string) $scheduledHours, (string) $workedHours, 2);
            } elseif ($workedHours == 0 && ! $isSaturday && ! $isSunday) {
                $absenceHours = $scheduledHours;
            }
        }

        // Art. 59 caput CLT: Limite de 2h extras por dia (não se aplica a feriados/DSR)
        $overtimeLimitExceeded = false;
        if ($overtimeHours50 > config('hr.clt.overtime_daily_limit_hours') && ! $isHoliday && ! $isDsr) {
            $overtimeLimitExceeded = true;
            Log::warning('Art. 59 CLT: Horas extras excedem limite de 2h/dia', [
                'user_id' => $userId,
                'date' => $date,
                'overtime_hours_50' => $overtimeHours50,
            ]);
        }

        // Art. 71 CLT: Intervalo intrajornada
        $breakCompliance = $this->calculateBreakCompliance($entries, $workedHours);

        // Art. 66 CLT: Intervalo interjornada (mínimo 11h entre jornadas)
        $interShiftHours = $this->calculateInterShiftRest($userId, $tenantId, $entries);

        // Hour bank calculation
        $hourBankBalance = 0;
        if ($rule->uses_hour_bank) {
            $previousBalance = JourneyEntry::where('user_id', $userId)
                ->where('date', '<', $date)
                ->orderByDesc('date')
                ->value('hour_bank_balance') ?? 0;

            $hourBankBalance = bcadd(
                (string) $previousBalance,
                bcsub((string) $overtimeHours50, (string) $absenceHours, 2),
                2
            );
        }

        return JourneyEntry::updateOrCreate(
            ['user_id' => $userId, 'date' => $date],
            [
                'tenant_id' => $tenantId,
                'journey_rule_id' => $rule->id,
                'scheduled_hours' => $scheduledHours,
                'worked_hours' => $workedHours,
                'overtime_hours_50' => max(0, $overtimeHours50),
                'overtime_hours_100' => max(0, $overtimeHours100),
                'night_hours' => $nightHours,
                'absence_hours' => max(0, $absenceHours),
                'hour_bank_balance' => $hourBankBalance,
                'overtime_limit_exceeded' => $overtimeLimitExceeded,
                'tolerance_applied' => $toleranceApplied,
                'break_compliance' => $breakCompliance,
                'inter_shift_hours' => $interShiftHours,
                'is_holiday' => $isHoliday,
                'is_dsr' => $isDsr,
                'status' => 'calculated',
            ]
        );
    }

    /**
     * Calculate entire month for a user.
     */
    public function calculateMonth(int $userId, string $yearMonth, int $tenantId): array
    {
        [$year, $month] = explode('-', $yearMonth);
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $entries = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $entries[] = $this->calculateDay($userId, $day->toDateString(), $tenantId);
        }

        return $entries;
    }

    /**
     * Get monthly summary for a user.
     */
    public function getMonthSummary(int $userId, string $yearMonth): array
    {
        [$year, $month] = explode('-', $yearMonth);

        $entries = JourneyEntry::where('user_id', $userId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        return [
            'user_id' => $userId,
            'year_month' => $yearMonth,
            'total_scheduled' => $entries->sum('scheduled_hours'),
            'total_worked' => $entries->sum('worked_hours'),
            'total_overtime_50' => $entries->sum('overtime_hours_50'),
            'total_overtime_100' => $entries->sum('overtime_hours_100'),
            'total_night' => $entries->sum('night_hours'),
            'total_absence' => $entries->sum('absence_hours'),
            'hour_bank_balance' => $entries->last()?->hour_bank_balance ?? 0,
            'days_worked' => $entries->where('worked_hours', '>', 0)->count(),
            'days_absent' => $entries->where('absence_hours', '>', 0)->count(),
            'holidays' => $entries->where('is_holiday', true)->count(),
            // DSR reflexo — Súmula TST 172 e 60
            'dsr_reflex' => $this->calculateDsrReflex($entries),
        ];
    }

    /**
     * Súmula TST 172 — DSR reflexo sobre horas extras habituais.
     * Súmula TST 60 — Adicional noturno habitual integra DSR.
     *
     * Fórmula: (total_HE / dias_úteis) * quantidade_DSR
     * Mesma lógica para horas noturnas.
     */
    private function calculateDsrReflex($entries): array
    {
        $dsrDays = $entries->where('is_dsr', true)->count()
            + $entries->where('is_holiday', true)->count();
        $workDays = $entries->where('worked_hours', '>', 0)
            ->where('is_dsr', false)
            ->where('is_holiday', false)
            ->count();

        if ($workDays === 0 || $dsrDays === 0) {
            return [
                'overtime_reflex_hours' => 0,
                'night_reflex_hours' => 0,
                'work_days' => $workDays,
                'dsr_days' => $dsrDays,
            ];
        }

        $totalOvertime = bcadd(
            (string) $entries->sum('overtime_hours_50'),
            (string) $entries->sum('overtime_hours_100'),
            4
        );
        $totalNight = (string) $entries->sum('night_hours');
        $workStr = (string) $workDays;
        $dsrStr = (string) $dsrDays;

        $otRate = bcdiv($totalOvertime, $workStr, 6);
        $otReflex = bcmul($otRate, $dsrStr, 4);

        $ntRate = bcdiv($totalNight, $workStr, 6);
        $ntReflex = bcmul($ntRate, $dsrStr, 4);

        return [
            'overtime_reflex_hours' => round((float) $otReflex, 2),
            'night_reflex_hours' => round((float) $ntReflex, 2),
            'work_days' => $workDays,
            'dsr_days' => $dsrDays,
        ];
    }

    /**
     * Get current hour bank balance for a user.
     */
    public function getHourBankBalance(int $userId): float
    {
        return (float) (JourneyEntry::where('user_id', $userId)
            ->orderByDesc('date')
            ->value('hour_bank_balance') ?? 0);
    }

    private function calculateNightMinutes(Carbon $start, Carbon $end, string $nightStart, string $nightEnd): int
    {
        $nightMinutes = 0;

        $iterator = $start->copy()->subDay()->startOfDay();
        $endIterator = $end->copy()->addDay()->startOfDay();

        while ($iterator->lte($endIterator)) {
            $nStart = $iterator->copy()->setTimeFromTimeString($nightStart);
            $nEnd = $iterator->copy();

            if (Carbon::parse($nightEnd)->lte(Carbon::parse($nightStart))) {
                $nEnd->addDay()->setTimeFromTimeString($nightEnd);
            } else {
                $nEnd->setTimeFromTimeString($nightEnd);
            }

            $overlapStart = $start->max($nStart);
            $overlapEnd = $end->min($nEnd);

            if ($overlapStart->lt($overlapEnd)) {
                $nightMinutes += $overlapStart->diffInMinutes($overlapEnd);
            }

            $iterator->addDay();
        }

        return $nightMinutes;
    }

    /**
     * Art. 71 CLT: Calculate break compliance.
     * >6h shift → min 1h break. 4-6h shift → min 15min break. <4h → none required.
     */
    private function calculateBreakCompliance($entries, float $workedHours): string
    {
        $breakMinutes = 0;
        foreach ($entries as $entry) {
            if ($entry->break_start && $entry->break_end) {
                $breakMinutes += $entry->break_start->diffInMinutes($entry->break_end);
            }
        }

        if ($workedHours > 6) {
            if ($breakMinutes >= config('hr.clt.intra_shift_break_6h_minutes')) {
                return 'compliant';
            }

            return $breakMinutes > 0 ? 'short_break' : 'missing_break';
        }

        if ($workedHours > 4) {
            if ($breakMinutes >= config('hr.clt.intra_shift_break_4h_minutes')) {
                return 'compliant';
            }

            return $breakMinutes > 0 ? 'short_break' : 'missing_break';
        }

        return 'compliant'; // < 4h, no break required
    }

    /**
     * Art. 66 CLT: Calculate inter-shift rest hours.
     * Minimum 11h rest between end of one shift and start of next.
     */
    private function calculateInterShiftRest(int $userId, int $tenantId, $entries): ?float
    {
        if ($entries->isEmpty()) {
            return null;
        }

        $firstClockIn = $entries->first()->clock_in;

        $previousEntry = TimeClockEntry::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->whereNotNull('clock_out')
            ->where('clock_out', '<', $firstClockIn)
            ->orderByDesc('clock_out')
            ->first();

        if (! $previousEntry) {
            return null;
        }

        $diffMin = (string) $previousEntry->clock_out->diffInMinutes($firstClockIn);
        $restHoursStr = bcdiv($diffMin, '60', 4);
        $restHours = round((float) $restHoursStr, 2);

        if ($restHours < config('hr.clt.inter_shift_min_hours')) {
            Log::warning('Art. 66 CLT: Intervalo interjornada inferior a 11h', [
                'user_id' => $userId,
                'previous_clock_out' => $previousEntry->clock_out->toDateTimeString(),
                'current_clock_in' => $firstClockIn->toDateTimeString(),
                'rest_hours' => $restHours,
            ]);
        }

        return $restHours;
    }

    /**
     * CLT art. 71: Enforce break compliance for a journey entry.
     * >6h shift → min 60min break. 4-6h shift → min 15min break. <4h → none required.
     *
     * @return string 'compliant', 'short_break', or 'missing_break'
     */
    public function enforceBreakCompliance(JourneyEntry $entry, float $workedHours, ?float $breakMinutes): string
    {
        $breakMinutes = $breakMinutes ?? 0;

        if ($workedHours > 6) {
            if ($breakMinutes >= config('hr.clt.intra_shift_break_6h_minutes')) {
                $status = 'compliant';
            } elseif ($breakMinutes > 0) {
                $status = 'short_break';
                Log::warning('Art. 71 CLT: Intervalo intrajornada inferior a 60min para jornada >6h', [
                    'user_id' => $entry->user_id,
                    'date' => $entry->date,
                    'break_minutes' => $breakMinutes,
                    'required_minutes' => 60,
                ]);
            } else {
                $status = 'missing_break';
                Log::warning('Art. 71 CLT: Intervalo intrajornada ausente para jornada >6h', [
                    'user_id' => $entry->user_id,
                    'date' => $entry->date,
                ]);
            }
        } elseif ($workedHours > 4) {
            if ($breakMinutes >= config('hr.clt.intra_shift_break_4h_minutes')) {
                $status = 'compliant';
            } elseif ($breakMinutes > 0) {
                $status = 'short_break';
                Log::warning('Art. 71 CLT: Intervalo intrajornada inferior a 15min para jornada 4-6h', [
                    'user_id' => $entry->user_id,
                    'date' => $entry->date,
                    'break_minutes' => $breakMinutes,
                    'required_minutes' => 15,
                ]);
            } else {
                $status = 'missing_break';
                Log::warning('Art. 71 CLT: Intervalo intrajornada ausente para jornada 4-6h', [
                    'user_id' => $entry->user_id,
                    'date' => $entry->date,
                ]);
            }
        } else {
            $status = 'compliant'; // <4h, no break required
        }

        $entry->break_compliance = $status;
        $entry->save();

        return $status;
    }

    /**
     * CLT art. 59: Check if overtime exceeds the 2h daily limit.
     *
     * @return bool True if the limit was exceeded
     */
    public function checkOvertimeLimit(JourneyEntry $entry, float $overtimeHours): bool
    {
        $exceeded = $overtimeHours > config('hr.clt.overtime_daily_limit_hours');

        if ($exceeded) {
            Log::warning('Art. 59 CLT: Horas extras excedem limite de 2h/dia', [
                'user_id' => $entry->user_id,
                'date' => $entry->date,
                'overtime_hours' => $overtimeHours,
                'max_allowed' => 2.0,
            ]);
        }

        $entry->overtime_limit_exceeded = $exceeded;
        $entry->save();

        return $exceeded;
    }

    /**
     * CLT art. 66: Check minimum 11h inter-shift rest.
     * Gets previous day's last clock-out and current day's first clock-in.
     *
     * @return float|null Hours between shifts, or null if insufficient data
     */
    public function checkInterShiftRest(int $userId, string $date): ?float
    {
        $currentDayEntries = TimeClockEntry::where('user_id', $userId)
            ->whereDate('clock_in', $date)
            ->whereNotNull('clock_out')
            ->orderBy('clock_in')
            ->get();

        if ($currentDayEntries->isEmpty()) {
            return null;
        }

        $firstClockIn = $currentDayEntries->first()->clock_in;

        $previousEntry = TimeClockEntry::where('user_id', $userId)
            ->whereNotNull('clock_out')
            ->where('clock_out', '<', $firstClockIn)
            ->orderByDesc('clock_out')
            ->first();

        if (! $previousEntry) {
            return null;
        }

        $diffMin = (string) $previousEntry->clock_out->diffInMinutes($firstClockIn);
        $restHoursStr = bcdiv($diffMin, '60', 4);
        $restHours = round((float) $restHoursStr, 2);

        if ($restHours < config('hr.clt.inter_shift_min_hours')) {
            Log::warning('Art. 66 CLT: Intervalo interjornada inferior a 11h', [
                'user_id' => $userId,
                'date' => $date,
                'previous_clock_out' => $previousEntry->clock_out->toDateTimeString(),
                'current_clock_in' => $firstClockIn->toDateTimeString(),
                'rest_hours' => $restHours,
            ]);
        }

        // Update the journey entry if it exists
        $journeyEntry = JourneyEntry::where('user_id', $userId)
            ->where('date', $date)
            ->first();

        if ($journeyEntry) {
            $journeyEntry->inter_shift_hours = Decimal::string($restHours);
            $journeyEntry->save();
        }

        return $restHours;
    }

    /**
     * Get the journey rule for a user (default or assigned).
     */
    private function getRuleForUser(int $userId, int $tenantId): JourneyRule
    {
        // Future: allow per-user rule assignment
        // For now, use the default rule for the tenant
        $rule = JourneyRule::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();

        if (! $rule) {
            // Create a default rule if none exists
            $rule = JourneyRule::create([
                'tenant_id' => $tenantId,
                'name' => 'CLT Padrão',
                'daily_hours' => 8.00,
                'weekly_hours' => 44.00,
                'overtime_weekday_pct' => 50,
                'overtime_weekend_pct' => 100,
                'overtime_holiday_pct' => 100,
                'night_shift_pct' => 20,
                'night_start' => '22:00',
                'night_end' => '05:00',
                'uses_hour_bank' => false,
                'hour_bank_expiry_months' => 6,
                'is_default' => true,
            ]);
        }

        return $rule;
    }
}
