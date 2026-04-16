<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Vacation calculation service — CLT Arts. 130, 134, 142-145.
 */
class VacationCalculationService
{
    /**
     * Calculate proportional vacation days (CLT Art 130).
     * 30 days for 12 months with no more than 5 unexcused absences.
     */
    public function calculateProportionalDays(int $monthsWorked, int $unexcusedAbsences = 0): int
    {
        if ($monthsWorked <= 0) {
            return 0;
        }

        // CLT Art 130 — Proportional reduction based on absences
        $baseDays = match (true) {
            $unexcusedAbsences <= 5 => 30,
            $unexcusedAbsences <= 14 => 24,
            $unexcusedAbsences <= 23 => 18,
            $unexcusedAbsences <= 32 => 12,
            default => 0,
        };

        return (int) floor(($baseDays / 12) * min($monthsWorked, 12));
    }

    /**
     * Validate vacation splitting (CLT Art 134).
     * - Max 3 periods
     * - At least one >= 14 days
     * - None < 5 days
     *
     * @param  array<int>  $periodDays  Array with days for each period
     * @return array{valid: bool, errors: array<string>}
     */
    public function validateSplitting(array $periodDays): array
    {
        $errors = [];

        if (count($periodDays) > 3) {
            $errors[] = 'CLT Art. 134: Férias podem ser fracionadas em no máximo 3 períodos.';
        }

        if (count($periodDays) > 1) {
            $maxPeriod = max($periodDays);
            if ($maxPeriod < 14) {
                $errors[] = 'CLT Art. 134 §1: Ao menos um período deve ter no mínimo 14 dias corridos.';
            }
        }

        foreach ($periodDays as $days) {
            if ($days < 5) {
                $errors[] = 'CLT Art. 134 §1: Nenhum período pode ser inferior a 5 dias corridos.';
                break;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Calculate vacation pay with 1/3 constitutional bonus (CLT Arts 142-145).
     * Includes abono pecuniário (Art 143 - selling up to 1/3 of vacation days).
     */
    public function calculateVacationPay(
        float $monthlySalary,
        int $days = 30,
        int $soldDays = 0,
        float $overtimeAvg = 0,
        float $dsrAvg = 0,
        float $nightShiftAvg = 0,
    ): array {
        // Sold days cannot exceed 1/3 of vacation days (Art 143)
        $maxSellable = (int) floor($days / 3);
        $soldDays = min($soldDays, $maxSellable);

        $totalMonthly = $monthlySalary + $overtimeAvg + $dsrAvg + $nightShiftAvg;
        $dailyRate = $totalMonthly / 30;

        $vacationDays = $days - $soldDays;
        $vacationSalary = round($dailyRate * $vacationDays, 2);
        $constitutionalBonus = round($vacationSalary / 3, 2);

        $abonoPecuniario = $soldDays > 0 ? round($dailyRate * $soldDays, 2) : 0;
        $abonoPecuniarioBonus = $soldDays > 0 ? round($abonoPecuniario / 3, 2) : 0;

        $grossTotal = $vacationSalary + $constitutionalBonus + $abonoPecuniario + $abonoPecuniarioBonus;

        return [
            'daily_rate' => $dailyRate,
            'vacation_days_enjoyed' => $vacationDays,
            'vacation_salary' => $vacationSalary,
            'constitutional_bonus' => $constitutionalBonus,
            'sold_days' => $soldDays,
            'max_sellable_days' => $maxSellable,
            'abono_pecuniario' => $abonoPecuniario,
            'abono_pecuniario_bonus' => $abonoPecuniarioBonus,
            'overtime_avg' => $overtimeAvg,
            'dsr_avg' => $dsrAvg,
            'night_shift_avg' => $nightShiftAvg,
            'gross_total' => $grossTotal,
        ];
    }

    /**
     * Calculate vacation deadline — employer must grant vacation before the
     * acquisition period ends (12 months from admission + 12 months concessive).
     */
    public function calculateDeadline(Carbon $admissionDate, int $acquisitionPeriod = 1): array
    {
        $acquisitionStart = $admissionDate->copy()->addMonths(12 * ($acquisitionPeriod - 1));
        $acquisitionEnd = $acquisitionStart->copy()->addMonths(12)->subDay();
        $concessiveEnd = $acquisitionEnd->copy()->addMonths(12);

        return [
            'acquisition_start' => $acquisitionStart->toDateString(),
            'acquisition_end' => $acquisitionEnd->toDateString(),
            'concessive_deadline' => $concessiveEnd->toDateString(),
            'is_overdue' => now()->greaterThan($concessiveEnd),
        ];
    }
}
