<?php

namespace Database\Factories;

use App\Models\SaasPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaasPlan>
 */
class SaasPlanFactory extends Factory
{
    protected $model = SaasPlan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Básico', 'Profissional', 'Enterprise']),
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
            'monthly_price' => $this->faker->randomFloat(2, 99, 999),
            'annual_price' => $this->faker->randomFloat(2, 999, 9999),
            'modules' => ['financial', 'calibration', 'scheduling', 'inventory'],
            'max_users' => $this->faker->randomElement([5, 15, 50]),
            'max_work_orders_month' => $this->faker->randomElement([100, 500, null]),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
