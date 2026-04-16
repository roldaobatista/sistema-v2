<?php

namespace Database\Factories;

use App\Models\QualityCorrectiveAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QualityCorrectiveActionFactory extends Factory
{
    protected $model = QualityCorrectiveAction::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => User::factory(),
            'description' => fake()->paragraph(),
            'status' => QualityCorrectiveAction::STATUS_OPEN,
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => QualityCorrectiveAction::STATUS_COMPLETED]);
    }
}
