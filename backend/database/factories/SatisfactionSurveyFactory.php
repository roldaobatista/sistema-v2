<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\SatisfactionSurvey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SatisfactionSurvey>
 */
class SatisfactionSurveyFactory extends Factory
{
    protected $model = SatisfactionSurvey::class;

    public function definition(): array
    {
        /** @var Customer $customer */
        $customer = Customer::factory()->create();

        return [
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'work_order_id' => null,
            'nps_score' => fake()->numberBetween(0, 10),
            'service_rating' => fake()->numberBetween(1, 5),
            'technician_rating' => fake()->numberBetween(1, 5),
            'timeliness_rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
            'channel' => 'system',
        ];
    }
}
