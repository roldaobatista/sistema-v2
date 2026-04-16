<?php

namespace App\Services;

use App\Models\HourBankTransaction;
use App\Models\PayrollLine;
use App\Models\Rescission;
use App\Models\TimeClockAdjustment;
use App\Models\User;
use App\Models\VacationBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RescissionService
{
    public function __construct(private LaborCalculationService $laborCalc) {}

    /**
     * Calculate a full rescission for the given user.
     */
    public function calculate(User $user, string $type, Carbon $terminationDate, ?string $noticeType = null, ?string $notes = null): Rescission
    {
        // Block rescission if there are pending clock adjustments
        $pendingAdjustments = TimeClockAdjustment::where('tenant_id', $user->current_tenant_id)
            ->whereHas('entry', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'pending')
            ->count();

        if ($pendingAdjustments > 0) {
            throw new \DomainException(
                "Existem {$pendingAdjustments} ajustes de ponto pendentes. Resolva antes de calcular rescisão."
            );
        }

        $salary = (float) ($user->salary ?? 0);
        $admissionDate = Carbon::parse($user->admission_date);
        $dailyRate = $salary / 30;

        // Salary balance (days worked in final month)
        $salaryBalanceDays = $terminationDate->day;
        $salaryBalanceValue = round($dailyRate * $salaryBalanceDays, 2);

        // Notice period: 30 + 3 per year of service, max 90
        $yearsOfService = $user->admission_date ? $admissionDate->diffInYears($terminationDate) : 0;
        $noticeDays = min(30 + ($yearsOfService * 3), 90);

        // Notice value depends on notice_type (worked/indemnified/waived) and rescission type
        $noticeValue = match ($noticeType ?? 'indemnified') {
            'indemnified' => match ($type) {
                Rescission::TYPE_ACORDO_MUTUO => round($dailyRate * $noticeDays * 0.5, 2), // 50% for mutual agreement
                Rescission::TYPE_JUSTA_CAUSA => 0, // No notice for just cause
                Rescission::TYPE_PEDIDO_DEMISSAO => 0, // Employee resigned, no notice payment
                default => round($dailyRate * $noticeDays, 2),
            },
            'worked' => 0, // Employee works the notice, no indemnification
            'waived' => 0,
            default => round($dailyRate * $noticeDays, 2),
        };

        // Proportional vacation
        $monthsSinceLastVacation = $this->getMonthsSinceLastVacation($user, $terminationDate);
        $vacationPropDays = (int) round($monthsSinceLastVacation * 2.5); // 30/12 = 2.5 days per month
        $vacationPropValue = round($dailyRate * $vacationPropDays, 2);
        $vacationBonusValue = round($vacationPropValue / 3, 2); // 1/3 constitucional

        // Overdue vacation (if any)
        $overdueVacation = $this->getOverdueVacation($user);
        $vacationOverdueDays = $overdueVacation['days'];
        $vacationOverdueValue = round($dailyRate * $vacationOverdueDays, 2);
        $vacationOverdueBonusValue = round($vacationOverdueValue / 3, 2);

        // 13th salary proportional
        $thirteenthMonths = $terminationDate->month;
        $thirteenthValue = round(($salary / 12) * $thirteenthMonths, 2);

        // FGTS balance estimate and penalty
        $monthsWorked = (int) $admissionDate->diffInMonths($terminationDate);
        $fgtsBalance = round($salary * 0.08 * $monthsWorked, 2);

        $fgtsPenaltyRate = 0;
        if ($type === Rescission::TYPE_ACORDO_MUTUO) {
            $fgtsPenaltyRate = 20;
        } elseif ($type === Rescission::TYPE_SEM_JUSTA_CAUSA || $type === Rescission::TYPE_TERMINO_CONTRATO) {
            $fgtsPenaltyRate = 40;
        }
        $fgtsPenaltyValue = round($fgtsBalance * $fgtsPenaltyRate / 100, 2);

        // Calculate advance deductions (Art. 462 CLT)
        $advanceDeductions = PayrollLine::where('user_id', $user->id)
            ->where('tenant_id', $user->current_tenant_id)
            ->whereHas('payroll', fn ($q) => $q->where('type', 'advance')->where('status', 'paid'))
            ->sum('net_salary');
        // Limit: cannot exceed 1 month salary
        $advanceDeductions = min((float) $advanceDeductions, $salary);

        // Hour bank payout/deduction
        $hourBankPayout = 0;
        $lastTransaction = HourBankTransaction::where('user_id', $user->id)
            ->where('tenant_id', $user->current_tenant_id)
            ->orderBy('id', 'desc')
            ->first();

        $hourBankBalance = $lastTransaction?->balance_after ?? 0;
        $hourlyRate = $salary / 220;

        if ($hourBankBalance > 0) {
            // Positive balance: pay as 50% overtime
            $hourBankPayout = round($hourBankBalance * $hourlyRate * 1.5, 2);
        } elseif ($hourBankBalance < 0) {
            // Negative balance: deduct if agreement allows
            $journeyRule = $user->journeyRule;
            if ($journeyRule?->allow_negative_hour_bank_deduction) {
                $hourBankPayout = round($hourBankBalance * $hourlyRate, 2); // negative value = deduction
            }
        }

        // For justa_causa: only salary balance + overdue vacation
        if ($type === Rescission::TYPE_JUSTA_CAUSA) {
            $vacationPropDays = 0;
            $vacationPropValue = 0;
            $vacationBonusValue = 0;
            $thirteenthMonths = 0;
            $thirteenthValue = 0;
            $noticeValue = 0;
            $noticeDays = 0;
            $noticeType = null;
            $fgtsPenaltyRate = 0;
            $fgtsPenaltyValue = 0;
            $hourBankPayout = 0;
        }

        // For pedido_demissao: no FGTS penalty, no notice payment
        if ($type === Rescission::TYPE_PEDIDO_DEMISSAO) {
            $fgtsPenaltyRate = 0;
            $fgtsPenaltyValue = 0;
            $noticeValue = 0;
            $noticeType = null;
        }

        // Totals
        $totalGross = $salaryBalanceValue + $noticeValue + $vacationPropValue + $vacationBonusValue
                     + $vacationOverdueValue + $vacationOverdueBonusValue + $thirteenthValue + $fgtsPenaltyValue;

        // Add hour bank payout if positive (deduction handled separately if negative)
        if ($hourBankPayout > 0) {
            $totalGross += $hourBankPayout;
        }

        // Deductions (INSS and IRRF apply on salary balance + 13th)
        $deductionBase = $salaryBalanceValue + $thirteenthValue;
        $inss = $this->laborCalc->calculateINSS($deductionBase);
        $irrf = $this->laborCalc->calculateIRRF($deductionBase, $inss['total_deduction'], $user->dependents_count ?? 0);

        $inssDeduction = $inss['total_deduction'];
        $irrfDeduction = $irrf['value'];

        // For justa_causa: minimal deductions
        if ($type === Rescission::TYPE_JUSTA_CAUSA) {
            $totalGross = $salaryBalanceValue + $vacationOverdueValue + $vacationOverdueBonusValue;
            $inssResult = $this->laborCalc->calculateINSS($salaryBalanceValue);
            $irrfResult = $this->laborCalc->calculateIRRF($salaryBalanceValue, $inssResult['total_deduction'], $user->dependents_count ?? 0);
            $inssDeduction = $inssResult['total_deduction'];
            $irrfDeduction = $irrfResult['value'];
        }

        $totalDeductions = $inssDeduction + $irrfDeduction + $advanceDeductions;

        // Add hour bank deduction if negative
        if ($hourBankPayout < 0) {
            $totalDeductions += abs($hourBankPayout);
        }

        $totalNet = $totalGross - $totalDeductions;

        return DB::transaction(function () use (
            $user, $type, $terminationDate, $noticeType, $noticeDays, $noticeValue,
            $salaryBalanceDays, $salaryBalanceValue, $vacationPropDays, $vacationPropValue,
            $vacationBonusValue, $vacationOverdueDays, $vacationOverdueValue, $vacationOverdueBonusValue,
            $thirteenthMonths, $thirteenthValue, $fgtsBalance, $fgtsPenaltyValue, $fgtsPenaltyRate,
            $advanceDeductions, $hourBankPayout,
            $inssDeduction, $irrfDeduction, $totalGross, $totalDeductions, $totalNet, $notes
        ) {
            return Rescission::create([
                'tenant_id' => $user->current_tenant_id,
                'user_id' => $user->id,
                'type' => $type,
                'termination_date' => $terminationDate,
                'last_work_day' => $terminationDate,
                'notice_type' => $noticeType,
                'notice_days' => $noticeDays,
                'notice_value' => $noticeValue,
                'salary_balance_days' => $salaryBalanceDays,
                'salary_balance_value' => $salaryBalanceValue,
                'vacation_proportional_days' => $vacationPropDays,
                'vacation_proportional_value' => $vacationPropValue,
                'vacation_bonus_value' => $vacationBonusValue,
                'vacation_overdue_days' => $vacationOverdueDays,
                'vacation_overdue_value' => $vacationOverdueValue,
                'vacation_overdue_bonus_value' => $vacationOverdueBonusValue,
                'thirteenth_proportional_months' => $thirteenthMonths,
                'thirteenth_proportional_value' => $thirteenthValue,
                'fgts_balance' => $fgtsBalance,
                'fgts_penalty_value' => $fgtsPenaltyValue,
                'fgts_penalty_rate' => $fgtsPenaltyRate,
                'advance_deductions' => $advanceDeductions,
                'hour_bank_payout' => $hourBankPayout,
                'inss_deduction' => $inssDeduction,
                'irrf_deduction' => $irrfDeduction,
                'total_gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net' => $totalNet,
                'status' => Rescission::STATUS_CALCULATED,
                'calculated_at' => now(),
                'calculated_by' => auth()->id(),
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Approve a rescission.
     */
    public function approve(Rescission $rescission, int $approvedBy): void
    {
        $rescission->update([
            'status' => Rescission::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);
    }

    /**
     * Mark rescission as paid.
     */
    public function markAsPaid(Rescission $rescission): void
    {
        $rescission->update([
            'status' => Rescission::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    /**
     * Cancel a rescission.
     */
    public function cancel(Rescission $rescission): void
    {
        $rescission->update([
            'status' => Rescission::STATUS_CANCELLED,
        ]);
    }

    /**
     * Generate TRCT HTML content.
     */
    public function generateTRCTHtml(Rescission $rescission): string
    {
        $rescission->loadMissing(['user', 'calculatedBy', 'approvedBy']);
        $user = $rescission->user;

        $typeLabel = Rescission::TYPE_LABELS[$rescission->type] ?? $rescission->type;
        $noticeLabel = $rescission->notice_type ? (Rescission::NOTICE_TYPE_LABELS[$rescission->notice_type] ?? $rescission->notice_type) : '—';

        $rows = [
            ['Saldo de Salário', "{$rescission->salary_balance_days} dias", number_format((float) $rescission->salary_balance_value, 2, ',', '.')],
            ['Aviso Prévio ('.$noticeLabel.')', "{$rescission->notice_days} dias", number_format((float) $rescission->notice_value, 2, ',', '.')],
            ['Férias Proporcionais', "{$rescission->vacation_proportional_days} dias", number_format((float) $rescission->vacation_proportional_value, 2, ',', '.')],
            ['1/3 Férias Proporcionais', '', number_format((float) $rescission->vacation_bonus_value, 2, ',', '.')],
            ['Férias Vencidas', "{$rescission->vacation_overdue_days} dias", number_format((float) $rescission->vacation_overdue_value, 2, ',', '.')],
            ['1/3 Férias Vencidas', '', number_format((float) $rescission->vacation_overdue_bonus_value, 2, ',', '.')],
            ['13º Proporcional', "{$rescission->thirteenth_proportional_months} meses", number_format((float) $rescission->thirteenth_proportional_value, 2, ',', '.')],
            ['Multa FGTS ('.number_format((float) $rescission->fgts_penalty_rate, 0).'%)', '', number_format((float) $rescission->fgts_penalty_value, 2, ',', '.')],
        ];

        // Add hour bank payout row if applicable
        if (($rescission->hour_bank_payout ?? 0) > 0) {
            $rows[] = ['Banco de Horas (pagamento)', '', number_format((float) $rescission->hour_bank_payout, 2, ',', '.')];
        }

        $earningsHtml = '';
        foreach ($rows as $row) {
            $earningsHtml .= "<tr><td>{$row[0]}</td><td class='center'>{$row[1]}</td><td class='right'>R$ {$row[2]}</td></tr>";
        }

        $deductionRows = [
            ['INSS', number_format((float) $rescission->inss_deduction, 2, ',', '.')],
            ['IRRF', number_format((float) $rescission->irrf_deduction, 2, ',', '.')],
        ];

        // Add advance deductions row if applicable
        if (($rescission->advance_deductions ?? 0) > 0) {
            $deductionRows[] = ['Adiantamentos (Art. 462 CLT)', number_format((float) $rescission->advance_deductions, 2, ',', '.')];
        }

        // Add hour bank deduction row if negative
        if (($rescission->hour_bank_payout ?? 0) < 0) {
            $deductionRows[] = ['Banco de Horas (débito)', number_format(abs((float) $rescission->hour_bank_payout), 2, ',', '.')];
        }

        $deductionsHtml = '';
        foreach ($deductionRows as $row) {
            $deductionsHtml .= "<tr><td>{$row[0]}</td><td class='right'>R$ {$row[1]}</td></tr>";
        }

        $totalGross = number_format((float) $rescission->total_gross, 2, ',', '.');
        $totalDeductions = number_format((float) $rescission->total_deductions, 2, ',', '.');
        $totalNet = number_format((float) $rescission->total_net, 2, ',', '.');
        $terminationDate = $rescission->termination_date->format('d/m/Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>TRCT - {$user->name}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; color: #333; }
        h1 { text-align: center; font-size: 16px; margin-bottom: 5px; }
        h2 { text-align: center; font-size: 13px; margin-top: 0; color: #666; }
        .header-info { margin-bottom: 20px; }
        .header-info table { width: 100%; border-collapse: collapse; }
        .header-info td { padding: 4px 8px; border: 1px solid #ccc; }
        .header-info .label { font-weight: bold; background: #f5f5f5; width: 180px; }
        table.breakdown { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table.breakdown th { background: #2563eb; color: white; padding: 6px 8px; text-align: left; }
        table.breakdown td { padding: 5px 8px; border-bottom: 1px solid #eee; }
        table.breakdown td.right { text-align: right; }
        table.breakdown td.center { text-align: center; }
        .totals { margin-top: 20px; }
        .totals table { width: 50%; margin-left: auto; border-collapse: collapse; }
        .totals td { padding: 6px 10px; font-weight: bold; }
        .totals td.right { text-align: right; }
        .totals .net { background: #dbeafe; font-size: 14px; }
        .signatures { margin-top: 60px; display: flex; justify-content: space-between; }
        .signature-box { text-align: center; width: 40%; }
        .signature-line { border-top: 1px solid #333; margin-top: 50px; padding-top: 5px; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <h1>TERMO DE RESCISÃO DO CONTRATO DE TRABALHO</h1>
    <h2>TRCT - Lei nº 13.467/2017</h2>

    <div class="header-info">
        <table>
            <tr>
                <td class="label">Empregado</td>
                <td>{$user->name}</td>
                <td class="label">CPF</td>
                <td>{$user->cpf}</td>
            </tr>
            <tr>
                <td class="label">Tipo de Rescisão</td>
                <td>{$typeLabel}</td>
                <td class="label">Data de Desligamento</td>
                <td>{$terminationDate}</td>
            </tr>
            <tr>
                <td class="label">Aviso Prévio</td>
                <td>{$noticeLabel}</td>
                <td class="label">Dias de Aviso</td>
                <td>{$rescission->notice_days}</td>
            </tr>
        </table>
    </div>

    <table class="breakdown">
        <thead>
            <tr>
                <th>Verba</th>
                <th style="text-align:center">Referência</th>
                <th style="text-align:right">Valor (R$)</th>
            </tr>
        </thead>
        <tbody>
            {$earningsHtml}
        </tbody>
    </table>

    <h3 style="margin-top:20px">Descontos</h3>
    <table class="breakdown">
        <thead>
            <tr>
                <th>Desconto</th>
                <th style="text-align:right">Valor (R$)</th>
            </tr>
        </thead>
        <tbody>
            {$deductionsHtml}
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Total Bruto:</td>
                <td class="right">R$ {$totalGross}</td>
            </tr>
            <tr>
                <td>Total Descontos:</td>
                <td class="right">R$ {$totalDeductions}</td>
            </tr>
            <tr class="net">
                <td>VALOR LÍQUIDO:</td>
                <td class="right">R$ {$totalNet}</td>
            </tr>
        </table>
    </div>

    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line">Empregador</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">Empregado - {$user->name}</div>
        </div>
    </div>

    <div class="footer">
        Documento gerado em {$rescission->created_at?->format('d/m/Y H:i')} | Sistema de Gestão
    </div>
</body>
</html>
HTML;
    }

    /**
     * Months since last vacation period started (for proportional vacation calc).
     */
    private function getMonthsSinceLastVacation(User $user, Carbon $date): int
    {
        $admissionDate = Carbon::parse($user->admission_date);

        // Check if there's a VacationBalance model
        if (class_exists(VacationBalance::class)) {
            $lastBalance = VacationBalance::where('user_id', $user->id)
                ->where('status', 'taken')
                ->orderByDesc('acquisition_end')
                ->first();

            if ($lastBalance) {
                $lastVacEnd = Carbon::parse($lastBalance->acquisition_end);

                return min((int) $lastVacEnd->diffInMonths($date), 12);
            }
        }

        // If no vacation taken, calculate from anniversary
        $totalMonths = (int) $admissionDate->diffInMonths($date);
        $monthsInCurrentPeriod = $totalMonths % 12;

        return $monthsInCurrentPeriod;
    }

    /**
     * Check for overdue vacation periods.
     */
    private function getOverdueVacation(User $user): array
    {
        if (class_exists(VacationBalance::class)) {
            $overdue = VacationBalance::where('user_id', $user->id)
                ->where('status', 'available')
                ->where('deadline', '<', now())
                ->sum('remaining_days');

            if ($overdue > 0) {
                return ['days' => (int) $overdue];
            }

            // Also check expired
            $expired = VacationBalance::where('user_id', $user->id)
                ->where('status', 'expired')
                ->sum('remaining_days');

            if ($expired > 0) {
                return ['days' => (int) $expired];
            }
        }

        return ['days' => 0];
    }
}
