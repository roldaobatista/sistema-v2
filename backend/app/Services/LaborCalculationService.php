<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LaborCalculationService
{
    /**
     * Get INSS brackets from DB with cache fallback.
     */
    private function getInssBrackets(int $year): array
    {
        return Cache::remember('inss_brackets_'.$year, 86400, function () use ($year) {
            $brackets = DB::table('inss_brackets')
                ->where('year', $year)
                ->orderBy('min_salary')
                ->get()
                ->map(fn ($b) => (array) $b)
                ->toArray();

            return ! empty($brackets) ? $brackets : $this->getDefaultInssBrackets();
        });
    }

    /**
     * Default INSS brackets (2026 values) used as fallback when DB has no data.
     */
    private function getDefaultInssBrackets(): array
    {
        return [
            ['min_salary' => 0.00, 'max_salary' => 1518.00, 'rate' => 7.5],
            ['min_salary' => 1518.01, 'max_salary' => 2793.88, 'rate' => 9.0],
            ['min_salary' => 2793.89, 'max_salary' => 4190.83, 'rate' => 12.0],
            ['min_salary' => 4190.84, 'max_salary' => 8157.41, 'rate' => 14.0],
        ];
    }

    /**
     * Get IRRF brackets from DB with cache fallback.
     */
    private function getIrrfBrackets(int $year): array
    {
        return Cache::remember('irrf_brackets_'.$year, 86400, function () use ($year) {
            $brackets = DB::table('irrf_brackets')
                ->where('year', $year)
                ->orderBy('min_base')
                ->get()
                ->map(fn ($b) => (array) $b)
                ->toArray();

            return ! empty($brackets) ? $brackets : $this->getDefaultIrrfBrackets();
        });
    }

    /**
     * Default IRRF brackets (2026 values) used as fallback when DB has no data.
     */
    private function getDefaultIrrfBrackets(): array
    {
        return [
            ['min_base' => 0.00, 'max_base' => 2428.80, 'rate' => 0.0, 'deduction' => 0.00],
            ['min_base' => 2428.81, 'max_base' => 2826.65, 'rate' => 7.5, 'deduction' => 182.16],
            ['min_base' => 2826.66, 'max_base' => 3751.05, 'rate' => 15.0, 'deduction' => 394.16],
            ['min_base' => 3751.06, 'max_base' => 4664.68, 'rate' => 22.5, 'deduction' => 675.49],
            ['min_base' => 4664.69, 'max_base' => 999999999.99, 'rate' => 27.5, 'deduction' => 908.73],
        ];
    }

    /**
     * INSS calculation (progressive - each bracket applies only to its range).
     */
    public function calculateINSS(float $grossSalary, ?int $year = null): array
    {
        $year = $year ?? now()->year;
        $brackets = $this->getInssBrackets($year);

        $totalDeduction = '0.00';
        $details = [];
        $grossString = number_format($grossSalary, 2, '.', '');

        foreach ($brackets as $bracket) {
            $bracket = (object) $bracket;
            if (bccomp($grossString, (string) $bracket->min_salary, 2) <= 0) {
                break;
            }

            // rangeMax = min(gross, max_salary)
            $rangeMax = bccomp($grossString, (string) $bracket->max_salary, 2) === -1
                ? $grossString
                : (string) $bracket->max_salary;

            // rangeBase = rangeMax - min_salary
            $rangeBase = bcsub($rangeMax, (string) $bracket->min_salary, 4);

            // deduction = rangeBase * (rate / 100)
            $rateFactor = bcdiv((string) $bracket->rate, '100', 6);
            $deduction = bcmul($rangeBase, $rateFactor, 4);
            $deductionRounded = round((float) $deduction, 2); // Banker's rounding
            $deductionRoundedStr = number_format($deductionRounded, 2, '.', '');

            $totalDeduction = bcadd($totalDeduction, $deductionRoundedStr, 2);

            $details[] = [
                'range' => "{$bracket->min_salary} - {$bracket->max_salary}",
                'rate' => (float) $bracket->rate,
                'base' => (float) $rangeBase,
                'value' => (float) $deductionRoundedStr,
            ];
        }

        return [
            'gross_salary' => $grossSalary,
            'total_deduction' => (float) $totalDeduction,
            'details' => $details,
        ];
    }

    /**
     * IRRF calculation.
     */
    public function calculateIRRF(float $grossSalary, float $inssDeduction, int $dependentsCount = 0, ?int $year = null): array
    {
        $year = $year ?? now()->year;
        $dependentDeductionPerUnit = '189.59';

        $dependentsDeduction = bcmul($dependentDeductionPerUnit, (string) $dependentsCount, 2);

        $base = bcsub(
            bcsub(number_format($grossSalary, 2, '.', ''), number_format($inssDeduction, 2, '.', ''), 2),
            $dependentsDeduction,
            2
        );

        if (bccomp($base, '0.00', 2) <= 0) {
            return ['base' => 0, 'rate' => 0, 'deduction' => 0, 'value' => 0, 'exempt' => true];
        }

        $brackets = $this->getIrrfBrackets($year);

        // Find the matching bracket (highest min_base <= base)
        $matchedBracket = null;
        foreach ($brackets as $b) {
            $b = (object) $b;
            if (bccomp($base, (string) $b->min_base, 2) >= 0) {
                $matchedBracket = $b;
            }
        }

        if (! $matchedBracket || (float) $matchedBracket->rate == 0) {
            return ['base' => (float) $base, 'rate' => 0, 'deduction' => 0, 'value' => 0, 'exempt' => true];
        }

        // value = (base * rate / 100) - bracket_deduction
        $rateFactor = bcdiv((string) $matchedBracket->rate, '100', 6);
        $tax = bcmul($base, $rateFactor, 4);
        $value = bcsub($tax, (string) $matchedBracket->deduction, 4);

        $valueRounded = max(0.00, round((float) $value, 2));

        return [
            'gross_salary' => $grossSalary,
            'inss_deduction' => $inssDeduction,
            'dependents_deduction' => (float) $dependentsDeduction,
            'base' => (float) $base,
            'rate' => (float) $matchedBracket->rate,
            'bracket_deduction' => (float) $matchedBracket->deduction,
            'value' => (float) $valueRounded,
            'exempt' => false,
        ];
    }

    /**
     * FGTS calculation (always 8%).
     */
    public function calculateFGTS(float $grossSalary): array
    {
        $grossStr = number_format($grossSalary, 2, '.', '');
        $value = bcmul($grossStr, '0.08', 4);

        return [
            'base' => $grossSalary,
            'rate' => 8.0,
            'value' => round((float) $value, 2),
        ];
    }

    /**
     * Vacation pay calculation (salary + overtime/DSR/night reflexes + 1/3 constitutional bonus).
     */
    public function calculateVacationPay(float $monthlySalary, int $days = 30, int $soldDays = 0, float $overtimeReflex = 0, float $dsrReflex = 0, float $nightShiftReflex = 0): array
    {
        $totalMonthly = bcadd(bcadd(bcadd(
            number_format($monthlySalary, 4, '.', ''),
            number_format($overtimeReflex, 4, '.', ''), 4),
            number_format($dsrReflex, 4, '.', ''), 4),
            number_format($nightShiftReflex, 4, '.', ''), 4
        );

        $dailyRate = bcdiv($totalMonthly, '30.00', 4);

        $vacationSalaryRaw = bcmul($dailyRate, (string) $days, 4);
        $vacationSalary = round((float) $vacationSalaryRaw, 2);

        $constitutionalBonusRaw = bcdiv((string) $vacationSalary, '3.00', 4);
        $constitutionalBonus = round((float) $constitutionalBonusRaw, 2);

        if ($soldDays > 0) {
            $abonoRaw = bcmul($dailyRate, (string) $soldDays, 4);
            $abonoPecuniario = round((float) $abonoRaw, 2);
            $abonoBonusRaw = bcdiv((string) $abonoPecuniario, '3.00', 4);
            $abonoPecuniarioBonus = round((float) $abonoBonusRaw, 2);
        } else {
            $abonoPecuniario = 0.00;
            $abonoPecuniarioBonus = 0.00;
        }

        $grossTotal = bcadd(bcadd(bcadd(
            number_format($vacationSalary, 2, '.', ''),
            number_format($constitutionalBonus, 2, '.', ''), 2),
            number_format($abonoPecuniario, 2, '.', ''), 2),
            number_format($abonoPecuniarioBonus, 2, '.', ''), 2
        );

        return [
            'daily_rate' => round((float) $dailyRate, 2),
            'vacation_days' => $days,
            'vacation_salary' => $vacationSalary,
            'constitutional_bonus' => $constitutionalBonus,
            'overtime_reflex' => $overtimeReflex,
            'dsr_reflex' => $dsrReflex,
            'night_shift_reflex' => $nightShiftReflex,
            'sold_days' => $soldDays,
            'abono_pecuniario' => $abonoPecuniario,
            'abono_pecuniario_bonus' => $abonoPecuniarioBonus,
            'gross_total' => (float) $grossTotal,
        ];
    }

    /**
     * 13th salary calculation (proportional to months worked).
     */
    public function calculateThirteenthSalary(float $monthlySalary, int $monthsWorked, bool $isSecondInstallment = false, float $overtimeAvg = 0, float $dsrAvg = 0, float $nightShiftAvg = 0, float $commissionAvg = 0): array
    {
        $totalMonthly = bcadd(bcadd(bcadd(bcadd(
            number_format($monthlySalary, 4, '.', ''),
            number_format($overtimeAvg, 4, '.', ''), 4),
            number_format($dsrAvg, 4, '.', ''), 4),
            number_format($nightShiftAvg, 4, '.', ''), 4),
            number_format($commissionAvg, 4, '.', ''), 4
        );

        $monthRatio = bcdiv($totalMonthly, '12.00', 4);
        $proportionalRaw = bcmul($monthRatio, (string) $monthsWorked, 4);
        $proportional = round((float) $proportionalRaw, 2);
        $proportionalStr = number_format($proportional, 2, '.', '');

        if ($isSecondInstallment) {
            $firstInstallmentRaw = bcdiv($proportionalStr, '2.00', 4);
            $firstInstallment = round((float) $firstInstallmentRaw, 2);
            $firstInstStr = number_format($firstInstallment, 2, '.', '');

            $inss = $this->calculateINSS($proportional);
            $inssStr = number_format($inss['total_deduction'], 2, '.', '');

            $irrf = $this->calculateIRRF($proportional, $inss['total_deduction']);
            $irrfStr = number_format($irrf['value'], 2, '.', '');

            $netValueStr = bcsub(bcsub(bcsub(
                $proportionalStr,
                $firstInstStr, 2),
                $inssStr, 2),
                $irrfStr, 2
            );

            return [
                'months_worked' => $monthsWorked,
                'gross_value' => $proportional,
                'overtime_avg' => $overtimeAvg,
                'dsr_avg' => $dsrAvg,
                'night_shift_avg' => $nightShiftAvg,
                'commission_avg' => $commissionAvg,
                'first_installment_paid' => $firstInstallment,
                'inss' => $inss['total_deduction'],
                'irrf' => $irrf['value'],
                'net_value' => (float) $netValueStr,
            ];
        }

        // First installment (50%, no deductions)
        $installmentRaw = bcdiv($proportionalStr, '2.00', 4);
        $installmentValue = round((float) $installmentRaw, 2);

        return [
            'months_worked' => $monthsWorked,
            'gross_value' => $proportional,
            'overtime_avg' => $overtimeAvg,
            'dsr_avg' => $dsrAvg,
            'night_shift_avg' => $nightShiftAvg,
            'commission_avg' => $commissionAvg,
            'installment_value' => $installmentValue,
            'deductions' => 0,
            'net_value' => $installmentValue,
        ];
    }

    /**
     * DSR calculation.
     */
    public function calculateDSR(float $overtimeTotal, float $commissionTotal, int $workDays, int $sundaysAndHolidays, float $nightShiftTotal = 0, float $hazardPremium = 0): array
    {
        $baseStr = bcadd(bcadd(bcadd(
            number_format($overtimeTotal, 4, '.', ''),
            number_format($commissionTotal, 4, '.', ''), 4),
            number_format($nightShiftTotal, 4, '.', ''), 4),
            number_format($hazardPremium, 4, '.', ''), 4
        );

        $base = round((float) $baseStr, 2);

        if ($workDays == 0) {
            return ['base' => 0, 'value' => 0];
        }

        $dailyRaw = bcdiv($baseStr, (string) $workDays, 6);
        $dsrRaw = bcmul($dailyRaw, (string) $sundaysAndHolidays, 4);
        $dsrValue = round((float) $dsrRaw, 2);

        return [
            'overtime_total' => $overtimeTotal,
            'commission_total' => $commissionTotal,
            'night_shift_total' => $nightShiftTotal,
            'hazard_premium' => $hazardPremium,
            'work_days' => $workDays,
            'sundays_and_holidays' => $sundaysAndHolidays,
            'base' => $base,
            'value' => $dsrValue,
        ];
    }

    /**
     * Overtime pay.
     */
    public function calculateOvertimePay(float $hourlyRate, float $hours, float $percentage = 50): float
    {
        $rateStr = number_format($hourlyRate, 4, '.', '');
        $hoursStr = number_format($hours, 4, '.', '');

        // factor = 1 + percentage/100
        $percentFactor = bcdiv((string) $percentage, '100.00', 4);
        $totalFactor = bcadd('1.00', $percentFactor, 4);

        $basePay = bcmul($rateStr, $hoursStr, 4);
        $rawTotal = bcmul($basePay, $totalFactor, 4);

        return round((float) $rawTotal, 2);
    }

    /**
     * Night shift premium.
     */
    public function calculateNightShiftPay(float $hourlyRate, float $hours, float $percentage = 20): float
    {
        $rateStr = number_format($hourlyRate, 4, '.', '');
        $hoursStr = number_format($hours, 4, '.', '');

        $percentFactor = bcdiv((string) $percentage, '100.00', 4);
        $basePay = bcmul($rateStr, $hoursStr, 4);
        $rawTotal = bcmul($basePay, $percentFactor, 4);

        return round((float) $rawTotal, 2);
    }

    /**
     * Hourly rate from monthly salary.
     */
    public function getHourlyRate(float $monthlySalary, float $monthlyHours = 220): float
    {
        $salaryStr = number_format($monthlySalary, 4, '.', '');
        $hoursStr = number_format($monthlyHours, 4, '.', '');

        $rawRate = bcdiv($salaryStr, $hoursStr, 4);

        return round((float) $rawRate, 2);
    }
}
