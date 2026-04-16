<?php

namespace Database\Factories;

use App\Models\HourBankPolicy;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HourBankPolicy>
 */
class HourBankPolicyFactory extends Factory
{
    protected $model = HourBankPolicy::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->word().' '.$this->faker->word().' '.$this->faker->word().' Bank Policy',
            'regime_type' => $this->faker->randomElement(['individual_mensal', 'individual_6meses', 'cct_anual']),
            'compensation_period_days' => 30,
            'max_positive_balance_minutes' => 6000,
            'max_negative_balance_minutes' => 2400,
            'block_on_negative_exceeded' => true,
            'auto_compensate' => false,
            'convert_expired_to_payment' => false,
            'overtime_50_multiplier' => 1.50,
            'overtime_100_multiplier' => 2.00,
            'requires_two_level_approval' => true,
            'is_active' => true,
        ];
    }

    public function semestral(): static
    {
        return $this->state(fn () => [
            'regime_type' => 'individual_6meses',
            'compensation_period_days' => 180,
        ]);
    }

    public function anual(): static
    {
        return $this->state(fn () => [
            'regime_type' => 'cct_anual',
            'compensation_period_days' => 365,
        ]);
    }

    public function autoCompensate(): static
    {
        return $this->state(fn () => [
            'auto_compensate' => true,
        ]);
    }

    public function convertExpired(): static
    {
        return $this->state(fn () => [
            'convert_expired_to_payment' => true,
        ]);
    }
}
