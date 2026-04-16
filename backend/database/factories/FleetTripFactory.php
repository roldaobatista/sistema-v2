<?php

namespace Database\Factories;

use App\Models\Fleet;
use App\Models\FleetTrip;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FleetTripFactory extends Factory
{
    protected $model = FleetTrip::class;

    public function definition(): array
    {
        $start = fake()->numberBetween(10000, 200000);

        return [
            'tenant_id' => Tenant::factory(),
            'fleet_id' => Fleet::factory(),
            'driver_user_id' => User::factory(),
            'date' => fake()->date(),
            'origin' => fake()->city(),
            'destination' => fake()->city(),
            'distance_km' => fake()->randomFloat(2, 5, 500),
            'purpose' => fake()->sentence(),
            'odometer_start' => $start,
            'odometer_end' => $start + fake()->numberBetween(10, 500),
        ];
    }
}
