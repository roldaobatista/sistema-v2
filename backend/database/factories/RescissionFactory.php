<?php

namespace Database\Factories;

use App\Models\Rescission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RescissionFactory extends Factory
{
    protected $model = Rescission::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(Rescission::TYPES);
        $salary = $this->faker->randomFloat(2, 1500, 15000);
        $dailyRate = $salary / 30;
        $balanceDays = $this->faker->numberBetween(1, 30);
        $balanceValue = round($dailyRate * $balanceDays, 2);
        $noticeDays = $this->faker->randomElement([30, 33, 36, 39, 42]);
        $noticeValue = in_array($type, ['sem_justa_causa', 'acordo_mutuo']) ? round($dailyRate * $noticeDays, 2) : 0;
        $vacPropDays = $this->faker->numberBetween(0, 30);
        $vacPropValue = round($dailyRate * $vacPropDays, 2);
        $vacBonusValue = round($vacPropValue / 3, 2);
        $thirteenthMonths = $this->faker->numberBetween(1, 12);
        $thirteenthValue = round(($salary / 12) * $thirteenthMonths, 2);
        $fgtsBalance = round($salary * 0.08 * $this->faker->numberBetween(6, 60), 2);
        $fgtsPenaltyRate = match ($type) {
            'acordo_mutuo' => 20,
            'sem_justa_causa', 'termino_contrato' => 40,
            default => 0,
        };
        $fgtsPenaltyValue = round($fgtsBalance * $fgtsPenaltyRate / 100, 2);

        $totalGross = $balanceValue + $noticeValue + $vacPropValue + $vacBonusValue + $thirteenthValue + $fgtsPenaltyValue;
        $inss = round($totalGross * 0.09, 2);
        $irrf = round(max(0, $totalGross * 0.075 - 158.40), 2);
        $totalDeductions = $inss + $irrf;
        $totalNet = $totalGross - $totalDeductions;

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'type' => $type,
            'termination_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'last_work_day' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'notice_type' => in_array($type, ['sem_justa_causa', 'acordo_mutuo']) ? $this->faker->randomElement(['worked', 'indemnified']) : null,
            'notice_days' => $noticeDays,
            'notice_value' => $noticeValue,
            'salary_balance_days' => $balanceDays,
            'salary_balance_value' => $balanceValue,
            'vacation_proportional_days' => $vacPropDays,
            'vacation_proportional_value' => $vacPropValue,
            'vacation_bonus_value' => $vacBonusValue,
            'vacation_overdue_days' => 0,
            'vacation_overdue_value' => 0,
            'vacation_overdue_bonus_value' => 0,
            'thirteenth_proportional_months' => $thirteenthMonths,
            'thirteenth_proportional_value' => $thirteenthValue,
            'fgts_balance' => $fgtsBalance,
            'fgts_penalty_value' => $fgtsPenaltyValue,
            'fgts_penalty_rate' => $fgtsPenaltyRate,
            'other_earnings' => 0,
            'other_deductions' => 0,
            'inss_deduction' => $inss,
            'irrf_deduction' => $irrf,
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net' => $totalNet,
            'status' => $this->faker->randomElement(Rescission::STATUSES),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function calculated(): static
    {
        return $this->state(fn () => [
            'status' => 'calculated',
            'calculated_at' => now(),
            'calculated_by' => User::factory(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'calculated_at' => now()->subDay(),
            'calculated_by' => User::factory(),
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'calculated_at' => now()->subDays(2),
            'calculated_by' => User::factory(),
            'approved_at' => now()->subDay(),
            'approved_by' => User::factory(),
            'paid_at' => now(),
        ]);
    }
}
