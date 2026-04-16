<?php

namespace Database\Factories;

use App\Models\OfflineMapRegion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfflineMapRegionFactory extends Factory
{
    protected $model = OfflineMapRegion::class;

    public function definition(): array
    {
        $lat = $this->faker->latitude(-25, -15);
        $lng = $this->faker->longitude(-55, -45);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->city().' Region',
            'bounds' => [
                'north' => $lat + 0.5,
                'south' => $lat - 0.5,
                'east' => $lng + 0.5,
                'west' => $lng - 0.5,
            ],
            'zoom_min' => 10,
            'zoom_max' => 16,
            'estimated_size_mb' => $this->faker->randomFloat(2, 5, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
