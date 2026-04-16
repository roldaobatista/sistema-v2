<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TravelAdvance;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelAdvance>
 */
class TravelAdvanceFactory extends Factory
{
    protected $model = TravelAdvance::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'travel_request_id' => TravelRequest::factory(),
            'user_id' => User::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 2000),
            'status' => 'pending',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'paid_at' => now()->format('Y-m-d'),
        ]);
    }
}
