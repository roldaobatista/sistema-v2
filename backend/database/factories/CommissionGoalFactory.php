<?php

namespace Database\Factories;

use App\Models\CommissionGoal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionGoalFactory extends Factory
{
    protected $model = CommissionGoal::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'period' => now()->format('Y-m'),
            'type' => CommissionGoal::TYPE_REVENUE,
            'target_amount' => $this->faker->randomFloat(2, 10000, 100000),
            'achieved_amount' => '0.00',
        ];
    }
}
