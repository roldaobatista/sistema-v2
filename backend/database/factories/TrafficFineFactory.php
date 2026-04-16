<?php

namespace Database\Factories;

use App\Models\FleetVehicle;
use App\Models\TrafficFine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrafficFine>
 */
class TrafficFineFactory extends Factory
{
    protected $model = TrafficFine::class;

    public function definition(): array
    {
        $vehicle = FleetVehicle::factory()->create();

        return [
            'tenant_id' => $vehicle->tenant_id,
            'fleet_vehicle_id' => $vehicle->id,
            'driver_id' => User::factory()->create(['tenant_id' => $vehicle->tenant_id])->id,
            'fine_date' => now()->toDateString(),
            'infraction_code' => fake()->bothify('INF-###'),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 50, 500),
            'points' => fake()->numberBetween(1, 7),
            'status' => 'pending',
            'due_date' => now()->addDays(15)->toDateString(),
        ];
    }
}
