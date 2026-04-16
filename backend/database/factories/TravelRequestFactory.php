<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRequest>
 */
class TravelRequestFactory extends Factory
{
    protected $model = TravelRequest::class;

    public function definition(): array
    {
        $departure = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $days = $this->faker->numberBetween(1, 5);
        $return = (clone $departure)->modify("+{$days} days");

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'status' => 'pending',
            'destination' => $this->faker->city(),
            'purpose' => $this->faker->sentence(),
            'departure_date' => $departure->format('Y-m-d'),
            'return_date' => $return->format('Y-m-d'),
            'estimated_days' => $days,
            'daily_allowance_amount' => $this->faker->randomFloat(2, 50, 300),
            'requires_vehicle' => $this->faker->boolean(30),
            'requires_overnight' => $days > 1,
            'rest_days_after' => 0,
            'overtime_authorized' => false,
        ];
    }

    public function approved(int $approverId): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_by' => $approverId,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => 'in_progress']);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }
}
