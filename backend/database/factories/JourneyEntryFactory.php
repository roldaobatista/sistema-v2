<?php

namespace Database\Factories;

use App\Models\JourneyEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JourneyEntryFactory extends Factory
{
    protected $model = JourneyEntry::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'date' => $this->faker->date(),
            'journey_rule_id' => null,
            // Campos legados (horas decimais)
            'scheduled_hours' => 8.00,
            'worked_hours' => 8.00,
            'overtime_hours_50' => 0,
            'overtime_hours_100' => 0,
            'night_hours' => 0,
            'absence_hours' => 0,
            'hour_bank_balance' => 0,
            'is_holiday' => false,
            'is_dsr' => false,
            'status' => 'calculated',
            // Campos Motor Operacional (minutos)
            'regime_type' => 'clt_mensal',
            'total_minutes_worked' => 480,
            'total_minutes_overtime' => 0,
            'total_minutes_travel' => 0,
            'total_minutes_wait' => 0,
            'total_minutes_break' => 60,
            'total_minutes_overnight' => 0,
            'total_minutes_oncall' => 0,
            'operational_approval_status' => 'pending',
            'hr_approval_status' => 'pending',
            'is_closed' => false,
        ];
    }

    public function withOvertime(float $hours50 = 2.0, float $hours100 = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'worked_hours' => 8.00 + $hours50 + $hours100,
            'overtime_hours_50' => $hours50,
            'overtime_hours_100' => $hours100,
            'total_minutes_overtime' => (int) (($hours50 + $hours100) * 60),
        ]);
    }

    public function holiday(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_holiday' => true,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'operational_approval_status' => 'approved',
            'operational_approved_at' => now(),
            'hr_approval_status' => 'approved',
            'hr_approved_at' => now(),
            'is_closed' => true,
        ]);
    }

    public function operationalApproved(): static
    {
        return $this->state(fn () => [
            'operational_approval_status' => 'approved',
            'operational_approved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->approved()->state(fn () => [
            'is_closed' => true,
        ]);
    }
}
