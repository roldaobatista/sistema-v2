<?php

namespace Database\Factories;

use App\Models\TravelExpenseItem;
use App\Models\TravelExpenseReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelExpenseItem>
 */
class TravelExpenseItemFactory extends Factory
{
    protected $model = TravelExpenseItem::class;

    public function definition(): array
    {
        return [
            'travel_expense_report_id' => TravelExpenseReport::factory(),
            'type' => $this->faker->randomElement(['alimentacao', 'transporte', 'hospedagem', 'pedagio', 'combustivel', 'outros']),
            'description' => $this->faker->sentence(3),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'expense_date' => $this->faker->dateTimeBetween('-7 days', 'now')->format('Y-m-d'),
            'is_within_policy' => true,
        ];
    }
}
