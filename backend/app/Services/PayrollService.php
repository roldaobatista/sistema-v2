<?php

namespace App\Services;

use App\Models\CommissionSettlement;
use App\Models\EmployeeBenefit;
use App\Models\Expense;
use App\Models\Holiday;
use App\Models\JourneyEntry;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\Payslip;
use App\Models\User;
use App\Models\VacationBalance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    public function __construct(
        private LaborCalculationService $laborCalc,
        private JourneyCalculationService $journeyCalc
    ) {}

    public function createPayroll(int $tenantId, string $referenceMonth, string $type = 'regular'): Payroll
    {
        return Payroll::create([
            'tenant_id' => $tenantId,
            'reference_month' => $referenceMonth,
            'type' => $type,
            'status' => 'draft',
        ]);
    }

    public function calculateForEmployee(Payroll $payroll, User $user): PayrollLine
    {
        $salary = (float) ($user->salary ?? 0);
        $hourlyRate = $this->laborCalc->getHourlyRate($salary);

        // Desviar para cálculos especiais conforme tipo de folha
        if (in_array($payroll->type, ['thirteenth_first', 'thirteenth_second'])) {
            return $this->calculateThirteenthForEmployee($payroll, $user, $salary, $hourlyRate);
        }

        if ($payroll->type === 'vacation') {
            return $this->calculateVacationForEmployee($payroll, $user, $salary, $hourlyRate);
        }

        return $this->calculateRegularForEmployee($payroll, $user, $salary, $hourlyRate);
    }

    private function calculateRegularForEmployee(Payroll $payroll, User $user, float $salary, float $hourlyRate): PayrollLine
    {
        // Get journey data for the month
        $journeySummary = $this->journeyCalc->getMonthSummary($user->id, $payroll->reference_month);

        // Map journey summary keys
        $ot50Hours = (float) ($journeySummary['total_overtime_50'] ?? 0);
        $ot100Hours = (float) ($journeySummary['total_overtime_100'] ?? 0);
        $nightHours = (float) ($journeySummary['total_night'] ?? 0);
        $workedDays = (int) ($journeySummary['days_worked'] ?? 0);
        $absenceDays = (int) ($journeySummary['days_absent'] ?? 0);

        // Calculate overtime
        $ot50Value = $this->laborCalc->calculateOvertimePay($hourlyRate, $ot50Hours, 50);
        $ot100Value = $this->laborCalc->calculateOvertimePay($hourlyRate, $ot100Hours, 100);

        // Night shift
        $nightValue = $this->laborCalc->calculateNightShiftPay($hourlyRate, $nightHours, 20);

        // Get commissions for the month (if any)
        $commissions = $this->getMonthlyCommissions($user, $payroll->reference_month);

        // DSR (Súmula 172 TST) — usar cálculo real baseado na jornada
        $monthStart = Carbon::parse($payroll->reference_month.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $referenceMonth = $payroll->reference_month;

        try {
            $dsrValue = $this->calculateRealDsr($user->id, $referenceMonth);
        } catch (\Throwable $e) {
            // Fallback to estimation if journey data not available
            $period = CarbonPeriod::create($monthStart, $monthEnd);
            $actualSundays = collect($period)->filter(fn ($d) => $d->isSunday())->count();
            $actualHolidays = Holiday::where('tenant_id', $payroll->tenant_id)
                ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count();
            $sundaysHolidays = $actualSundays + $actualHolidays;
            $workDaysInMonth = $period->count() - $sundaysHolidays;

            $dsr = $this->laborCalc->calculateDSR(
                $ot50Value + $ot100Value + $nightValue,
                $commissions,
                max($workDaysInMonth, 1),
                $sundaysHolidays
            );
            $dsrValue = $dsr['value'];
            Log::warning("DSR fallback to estimate for user {$user->id}: {$e->getMessage()}");
        }

        $dailyRate = $salary / 30;
        $absenceValue = round($dailyRate * $absenceDays, 2);

        // Process expired hour bank hours
        $hourBankPayoutHours = 0;
        $hourBankPayoutValue = 0;

        try {
            $expiryService = app(HourBankExpiryService::class);
            $expiryResult = $expiryService->processExpiry($user->id, $payroll->tenant_id);
            $expiredHours = abs($expiryResult['expired_hours'] ?? 0);
            if ($expiredHours > 0) {
                $hourBankPayoutHours = $expiredHours;
                $hourBankPayoutValue = round($expiredHours * $hourlyRate * 1.5, 2);
            }
        } catch (\Throwable $e) {
            Log::warning("Hour bank expiry failed for user {$user->id}: {$e->getMessage()}");
        }

        // Benefits deductions
        $benefits = $this->getBenefitDeductions($user);

        // Proportional benefit deductions for absences
        $vtDeduction = 0;
        $vrDeduction = 0;

        try {
            $journeyEntries = JourneyEntry::where('user_id', $user->id)
                ->whereBetween('date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                ->get();
            $workingDaysInMonth = $journeyEntries->filter(fn ($e) => ! $e->is_dsr && ! $e->is_holiday)->count();
            $absenceHours = $journeyEntries->sum('absence_hours');
            $dailyHours = $user->journeyRule?->daily_hours ?? 8;
            $proportionalAbsenceDays = round($absenceHours / $dailyHours, 1);
        } catch (\Throwable $e) {
            $workingDaysInMonth = 0;
            $proportionalAbsenceDays = 0;
        }

        if ($proportionalAbsenceDays > 0 && ($workingDaysInMonth ?? 0) > 0) {
            $vtBenefit = EmployeeBenefit::where('user_id', $user->id)
                ->where('type', 'vt')->where('is_active', true)->first();
            $vrBenefit = EmployeeBenefit::where('user_id', $user->id)
                ->where('type', 'vr')->where('is_active', true)->first();

            if ($vtBenefit) {
                $vtDeduction = round(($vtBenefit->value / $workingDaysInMonth) * $proportionalAbsenceDays, 2);
            }
            if ($vrBenefit) {
                $vrDeduction = round(($vrBenefit->value / $workingDaysInMonth) * $proportionalAbsenceDays, 2);
            }
        }

        // Gross salary (include hour bank payout as earning)
        $gross = $salary + $ot50Value + $ot100Value + $nightValue + $dsrValue + $commissions + $hourBankPayoutValue - $absenceValue;

        // INSS
        $inss = $this->laborCalc->calculateINSS($gross);

        // IRRF
        $dependentsCount = $user->dependents_count ?? 0;
        $irrf = $this->laborCalc->calculateIRRF($gross, $inss['total_deduction'], $dependentsCount);

        // FGTS
        $fgts = $this->laborCalc->calculateFGTS($gross);

        // Net
        $totalDeductions = $inss['total_deduction'] + $irrf['value']
            + $benefits['transportation'] + $benefits['meal']
            + $benefits['health_insurance'] + $benefits['other']
            + $vtDeduction + $vrDeduction;
        $net = $gross - $totalDeductions;

        return PayrollLine::create([
            'payroll_id' => $payroll->id,
            'user_id' => $user->id,
            'tenant_id' => $payroll->tenant_id,
            'base_salary' => $salary,
            'gross_salary' => round($gross, 2),
            'net_salary' => round($net, 2),
            'overtime_50_hours' => $ot50Hours,
            'overtime_50_value' => $ot50Value,
            'overtime_100_hours' => $ot100Hours,
            'overtime_100_value' => $ot100Value,
            'night_hours' => $nightHours,
            'night_shift_value' => $nightValue,
            'dsr_value' => $dsrValue,
            'commission_value' => $commissions,
            'inss_employee' => $inss['total_deduction'],
            'irrf' => $irrf['value'],
            'fgts_value' => $fgts['value'],
            'inss_employer_value' => round($gross * 0.20, 2),
            'transportation_discount' => $benefits['transportation'],
            'meal_discount' => $benefits['meal'],
            'health_insurance_discount' => $benefits['health_insurance'],
            'other_deductions' => $benefits['other'],
            'worked_days' => $workedDays,
            'absence_days' => $absenceDays,
            'absence_value' => $absenceValue,
            'hour_bank_payout_hours' => $hourBankPayoutHours,
            'hour_bank_payout_value' => $hourBankPayoutValue,
            'vt_deduction' => $vtDeduction,
            'vr_deduction' => $vrDeduction,
        ]);
    }

    /**
     * Cálculo do 13º salário com reflexos de HE, DSR, noturno e comissões (CLT).
     */
    private function calculateThirteenthForEmployee(Payroll $payroll, User $user, float $salary, float $hourlyRate): PayrollLine
    {
        $isSecond = $payroll->type === 'thirteenth_second';
        $refDate = Carbon::parse($payroll->reference_month.'-01');
        $admissionDate = Carbon::parse($user->admission_date);

        // Meses trabalhados no ano (>= 15 dias no mês conta como mês cheio)
        $startOfYear = $refDate->copy()->startOfYear();
        $effectiveStart = $admissionDate->gt($startOfYear) ? $admissionDate : $startOfYear;
        $monthsWorked = (int) $effectiveStart->diffInMonths($refDate->endOfMonth()) + 1;
        $monthsWorked = min($monthsWorked, 12);

        // Reflexos: média dos últimos 12 meses (ou meses trabalhados)
        $reflexes = $this->getAverageReflexes($user->id, $payroll->reference_month, $hourlyRate, min($monthsWorked, 12));

        $thirteenth = $this->laborCalc->calculateThirteenthSalary(
            $salary,
            $monthsWorked,
            $isSecond,
            $reflexes['overtime_avg'],
            $reflexes['dsr_avg'],
            $reflexes['night_shift_avg'],
            $reflexes['commission_avg']
        );

        $gross = $thirteenth['gross_value'];
        $dependentsCount = $user->dependents_count ?? 0;

        if ($isSecond) {
            $inss = $this->laborCalc->calculateINSS($gross);
            $irrf = $this->laborCalc->calculateIRRF($gross, $inss['total_deduction'], $dependentsCount);
            $fgts = $this->laborCalc->calculateFGTS($gross);
            $net = $thirteenth['net_value'];
        } else {
            $inss = ['total_deduction' => 0];
            $irrf = ['value' => 0];
            $fgts = $this->laborCalc->calculateFGTS($gross);
            $net = $thirteenth['net_value'];
        }

        return PayrollLine::create([
            'payroll_id' => $payroll->id,
            'user_id' => $user->id,
            'tenant_id' => $payroll->tenant_id,
            'base_salary' => $salary,
            'gross_salary' => round($gross, 2),
            'net_salary' => round($net, 2),
            'overtime_50_hours' => 0,
            'overtime_50_value' => round($reflexes['overtime_avg'], 2),
            'overtime_100_hours' => 0,
            'overtime_100_value' => 0,
            'night_hours' => 0,
            'night_shift_value' => round($reflexes['night_shift_avg'], 2),
            'dsr_value' => round($reflexes['dsr_avg'], 2),
            'commission_value' => round($reflexes['commission_avg'], 2),
            'thirteenth_value' => round($gross, 2),
            'thirteenth_months' => $monthsWorked,
            'inss_employee' => $inss['total_deduction'],
            'irrf' => $irrf['value'],
            'fgts_value' => $fgts['value'],
            'inss_employer_value' => round($gross * 0.20, 2),
            'transportation_discount' => 0,
            'meal_discount' => 0,
            'health_insurance_discount' => 0,
            'other_deductions' => 0,
            'worked_days' => $monthsWorked * 30,
            'absence_days' => 0,
            'absence_value' => 0,
        ]);
    }

    /**
     * Cálculo de férias com reflexos de HE, DSR e noturno (CLT art. 142 §5).
     */
    private function calculateVacationForEmployee(Payroll $payroll, User $user, float $salary, float $hourlyRate): PayrollLine
    {
        // Reflexos: média dos últimos 12 meses do período aquisitivo
        $reflexes = $this->getAverageReflexes($user->id, $payroll->reference_month, $hourlyRate, 12);

        $vacation = $this->laborCalc->calculateVacationPay(
            $salary,
            30,
            0,
            $reflexes['overtime_avg'],
            $reflexes['dsr_avg'],
            $reflexes['night_shift_avg']
        );

        $gross = $vacation['gross_total'];
        $dependentsCount = $user->dependents_count ?? 0;

        $inss = $this->laborCalc->calculateINSS($gross);
        $irrf = $this->laborCalc->calculateIRRF($gross, $inss['total_deduction'], $dependentsCount);
        $fgts = $this->laborCalc->calculateFGTS($gross);

        $totalDeductions = $inss['total_deduction'] + $irrf['value'];
        $net = $gross - $totalDeductions;

        $line = PayrollLine::create([
            'payroll_id' => $payroll->id,
            'user_id' => $user->id,
            'tenant_id' => $payroll->tenant_id,
            'base_salary' => $salary,
            'gross_salary' => round($gross, 2),
            'net_salary' => round($net, 2),
            'overtime_50_hours' => 0,
            'overtime_50_value' => round($reflexes['overtime_avg'], 2),
            'overtime_100_hours' => 0,
            'overtime_100_value' => 0,
            'night_hours' => 0,
            'night_shift_value' => round($reflexes['night_shift_avg'], 2),
            'dsr_value' => round($reflexes['dsr_avg'], 2),
            'commission_value' => 0,
            'vacation_days' => 30,
            'vacation_value' => round($vacation['vacation_salary'], 2),
            'vacation_bonus' => round($vacation['constitutional_bonus'], 2),
            'inss_employee' => $inss['total_deduction'],
            'irrf' => $irrf['value'],
            'fgts_value' => $fgts['value'],
            'inss_employer_value' => round($gross * 0.20, 2),
            'transportation_discount' => 0,
            'meal_discount' => 0,
            'health_insurance_discount' => 0,
            'other_deductions' => 0,
            'worked_days' => 30,
            'absence_days' => 0,
            'absence_value' => 0,
        ]);

        // Update VacationBalance after generating vacation payroll
        try {
            $vacationBalance = VacationBalance::where('user_id', $line->user_id)
                ->where('tenant_id', $payroll->tenant_id)
                ->whereIn('status', ['available', 'partially_taken', 'accruing'])
                ->orderBy('acquisition_start')
                ->first();

            if ($vacationBalance) {
                $vacationDays = $line->vacation_days ?? 30;
                $vacationBalance->taken_days = ($vacationBalance->taken_days ?? 0) + $vacationDays;

                if (($vacationBalance->taken_days + ($vacationBalance->sold_days ?? 0)) >= ($vacationBalance->total_days ?? 30)) {
                    $vacationBalance->status = 'taken';
                } else {
                    $vacationBalance->status = 'partially_taken';
                }

                $vacationBalance->save();
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to update VacationBalance for user {$line->user_id}: {$e->getMessage()}");
        }

        return $line;
    }

    public function calculateAll(Payroll $payroll): void
    {
        // Get all active employees for this tenant
        $employees = User::where('tenant_id', $payroll->tenant_id)
            ->where('is_active', true)
            ->whereNotNull('salary')
            ->whereNotNull('admission_date')
            ->get();

        DB::transaction(function () use ($payroll, $employees) {
            // Clear existing lines
            $payroll->lines()->delete();

            foreach ($employees as $employee) {
                $this->calculateForEmployee($payroll, $employee);
            }

            // Update payroll totals
            $payroll->update([
                'total_gross' => $payroll->lines()->sum('gross_salary'),
                'total_deductions' => $payroll->lines()->sum('inss_employee')
                    + $payroll->lines()->sum('irrf')
                    + $payroll->lines()->sum('transportation_discount')
                    + $payroll->lines()->sum('meal_discount')
                    + $payroll->lines()->sum('health_insurance_discount')
                    + $payroll->lines()->sum('other_deductions'),
                'total_net' => $payroll->lines()->sum('net_salary'),
                'total_fgts' => $payroll->lines()->sum('fgts_value'),
                'total_inss_employer' => $payroll->lines()->sum('inss_employer_value'),
                'employee_count' => $payroll->lines()->count(),
                'status' => 'calculated',
                'calculated_at' => now(),
                'calculated_by' => auth()->id(),
            ]);
        });
    }

    public function approve(Payroll $payroll, int $approvedBy): void
    {
        $payroll->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);
    }

    public function markAsPaid(Payroll $payroll): void
    {
        DB::transaction(function () use ($payroll) {
            $payroll->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $this->generateExpensesFromPayroll($payroll);
        });
    }

    /**
     * Gera despesas no módulo financeiro a partir da folha paga.
     */
    private function generateExpensesFromPayroll(Payroll $payroll): void
    {
        $payroll->load('lines.user:id,name');
        $typeLabels = [
            'regular' => 'Folha de Pagamento',
            'thirteenth_first' => '13º Salário (1ª parcela)',
            'thirteenth_second' => '13º Salário (2ª parcela)',
            'vacation' => 'Férias',
            'rescission' => 'Rescisão',
            'advance' => 'Adiantamento',
        ];
        $typeLabel = $typeLabels[$payroll->type] ?? 'Folha de Pagamento';
        $refMonth = Carbon::parse($payroll->reference_month.'-01')->format('m/Y');

        foreach ($payroll->lines as $line) {
            // Evitar duplicatas
            if (Expense::where('payroll_line_id', $line->id)->exists()) {
                continue;
            }

            // Net salary expense
            Expense::create([
                'tenant_id' => $payroll->tenant_id,
                'description' => "{$typeLabel} - {$line->user->name} - {$refMonth}",
                'amount' => $line->net_salary,
                'expense_date' => $payroll->paid_at ?? now(),
                'status' => 'approved',
                'payment_method' => 'transfer',
                'created_by' => auth()->id(),
                'approved_by' => $payroll->approved_by,
                'payroll_id' => $payroll->id,
                'payroll_line_id' => $line->id,
                'notes' => "Gerado automaticamente da folha #{$payroll->id}",
            ]);

            // INSS Patronal (20% of gross)
            $inssPatronal = round(($line->gross_salary ?? 0) * 0.20, 2);
            if ($inssPatronal > 0) {
                Expense::create([
                    'tenant_id' => $payroll->tenant_id,
                    'description' => 'INSS Patronal - '.($line->user->name ?? 'N/A')." - {$refMonth}",
                    'amount' => $inssPatronal,
                    'expense_date' => $payroll->paid_at ?? now(),
                    'status' => 'pending',
                    'created_by' => auth()->id() ?? $payroll->calculated_by,
                    'payroll_id' => $payroll->id,
                ]);
            }

            // FGTS (8% of gross)
            $fgtsValue = round(($line->gross_salary ?? 0) * 0.08, 2);
            if ($fgtsValue > 0) {
                Expense::create([
                    'tenant_id' => $payroll->tenant_id,
                    'description' => 'FGTS - '.($line->user->name ?? 'N/A')." - {$refMonth}",
                    'amount' => $fgtsValue,
                    'expense_date' => $payroll->paid_at ?? now(),
                    'status' => 'pending',
                    'created_by' => auth()->id() ?? $payroll->calculated_by,
                    'payroll_id' => $payroll->id,
                ]);
            }
        }
    }

    public function generatePayslips(Payroll $payroll): void
    {
        $payroll->load('lines');

        foreach ($payroll->lines as $line) {
            Payslip::updateOrCreate(
                ['payroll_line_id' => $line->id],
                [
                    'user_id' => $line->user_id,
                    'tenant_id' => $payroll->tenant_id,
                    'reference_month' => $payroll->reference_month,
                    'digital_signature_hash' => hash('sha256', json_encode($line->toArray()).config('app.key')),
                ]
            );
        }
    }

    // ── Journey validation & real DSR ──

    public function validateJourneyCompleteness(int $userId, string $referenceMonth): void
    {
        $start = Carbon::createFromFormat('Y-m', $referenceMonth)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $expectedDays = $start->daysInMonth;

        $actualDays = JourneyEntry::where('user_id', $userId)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->count();

        if ($actualDays < $expectedDays) {
            $missingDates = [];
            for ($d = 0; $d < $expectedDays; $d++) {
                $date = $start->copy()->addDays($d);
                $exists = JourneyEntry::where('user_id', $userId)
                    ->where('date', $date->format('Y-m-d'))
                    ->exists();
                if (! $exists) {
                    $missingDates[] = $date->format('Y-m-d');
                }
            }

            throw new \DomainException("Jornada incompleta para {$referenceMonth}. Faltam ".count($missingDates).' dia(s): '.implode(', ', array_slice($missingDates, 0, 5)));
        }
    }

    public function calculateRealDsr(int $userId, string $referenceMonth): float
    {
        $start = Carbon::createFromFormat('Y-m', $referenceMonth)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $user = User::findOrFail($userId);
        $hourlyRate = ($user->salary ?? 0) / 220;

        $entries = JourneyEntry::where('user_id', $userId)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get();

        if ($entries->isEmpty()) {
            return 0;
        }

        $workDays = $entries->filter(fn ($e) => ! $e->is_dsr && ! $e->is_holiday)->count();
        $dsrDays = $entries->filter(fn ($e) => $e->is_dsr || $e->is_holiday)->count();

        if ($workDays === 0) {
            return 0;
        }

        $totalOvertimeValue = $entries->sum('overtime_hours_50') * $hourlyRate * 1.5
            + $entries->sum('overtime_hours_100') * $hourlyRate * 2.0;
        $totalNightValue = $entries->sum('night_hours') * $hourlyRate * 0.2;

        return round(($totalOvertimeValue + $totalNightValue) / $workDays * $dsrDays, 2);
    }

    // ── Helper methods ──

    private function getMonthlyCommissions(User $user, string $referenceMonth): float
    {
        if (! class_exists(CommissionSettlement::class)) {
            return 0;
        }

        $start = Carbon::parse($referenceMonth.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return (float) (CommissionSettlement::where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount') ?? 0);
    }

    /**
     * Calcula média mensal de HE, DSR, adicional noturno e comissões
     * para reflexos em 13º e férias (CLT - Súmula 347 TST).
     *
     * JourneyEntry armazena horas, não valores. Convertemos usando o salário-hora.
     */
    public function getAverageReflexes(int $userId, string $referenceMonth, float $hourlyRate, int $months = 12): array
    {
        $end = Carbon::parse($referenceMonth.'-01')->startOfMonth();
        $start = $end->copy()->subMonths($months);

        $isMySQL = DB::connection()->getDriverName() === 'mysql';
        $monthExpr = $isMySQL ? 'DATE_FORMAT(date, "%Y-%m")' : 'strftime("%Y-%m", date)';

        $entries = JourneyEntry::where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->copy()->subDay()->toDateString()])
            ->selectRaw("
                SUM(overtime_hours_50) as total_ot50_hours,
                SUM(overtime_hours_100) as total_ot100_hours,
                SUM(night_hours) as total_night_hours,
                COUNT(DISTINCT {$monthExpr}) as months_counted
            ")
            ->first();

        $monthsCounted = max((int) ($entries->months_counted ?? 1), 1);

        // Converter horas em valores monetários
        $totalOt50Value = (float) ($entries->total_ot50_hours ?? 0) * $hourlyRate * 1.5;
        $totalOt100Value = (float) ($entries->total_ot100_hours ?? 0) * $hourlyRate * 2.0;
        $totalNightValue = (float) ($entries->total_night_hours ?? 0) * $hourlyRate * 0.2;

        $overtimeAvg = round(($totalOt50Value + $totalOt100Value) / $monthsCounted, 2);
        $nightShiftAvg = round($totalNightValue / $monthsCounted, 2);

        // DSR sobre HE: usar cálculo real baseado na jornada quando disponível
        try {
            $dsrAvg = $this->calculateRealDsr($userId, $referenceMonth);
        } catch (\Throwable $e) {
            // Fallback to estimation if journey data not available
            $dsrAvg = round($overtimeAvg * 0.2, 2);
            Log::warning("DSR fallback to estimate for user {$userId}: {$e->getMessage()}");
        }

        // Comissões
        $commissionAvg = 0;
        if (class_exists(CommissionSettlement::class)) {
            $totalCommissions = (float) CommissionSettlement::where('user_id', $userId)
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$start, $end->copy()->subDay()])
                ->sum('amount');
            $commissionAvg = round($totalCommissions / $monthsCounted, 2);
        }

        return [
            'overtime_avg' => $overtimeAvg,
            'dsr_avg' => $dsrAvg,
            'night_shift_avg' => $nightShiftAvg,
            'commission_avg' => $commissionAvg,
            'months_counted' => $monthsCounted,
        ];
    }

    private function getBenefitDeductions(User $user): array
    {
        $benefits = EmployeeBenefit::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        return [
            'transportation' => (float) $benefits->where('type', 'vt')->sum('employee_contribution'),
            'meal' => (float) $benefits->whereIn('type', ['vr', 'va'])->sum('employee_contribution'),
            'health_insurance' => (float) $benefits->whereIn('type', ['health', 'dental'])->sum('employee_contribution'),
            'other' => (float) $benefits->whereNotIn('type', ['vt', 'vr', 'va', 'health', 'dental'])->sum('employee_contribution'),
        ];
    }
}
