<?php

namespace Database\Factories;

use App\Models\Fleet;
use App\Models\FleetMaintenance;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class FleetMaintenanceFactory extends Factory
{
    protected $model = FleetMaintenance::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'fleet_id' => Fleet::factory(),
            'type' => 'preventive',
            'status' => 'scheduled',
            'cost' => $this->faker->randomFloat(2, 50, 1000),
            'date' => $this->faker->date(),
            'notes' => $this->faker->sentence(),
            'odometer' => $this->faker->numberBetween(10000, 150000),
        ];
    }
}
