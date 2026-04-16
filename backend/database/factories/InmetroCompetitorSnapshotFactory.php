<?php

namespace Database\Factories;

use App\Models\InmetroCompetitorSnapshot;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InmetroCompetitorSnapshotFactory extends Factory
{
    protected $model = InmetroCompetitorSnapshot::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'competitor_id' => null,
            'snapshot_type' => 'monthly',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'instrument_count' => $this->faker->numberBetween(100, 5000),
            'repair_count' => $this->faker->numberBetween(10, 200),
            'new_instruments' => $this->faker->numberBetween(5, 50),
            'lost_instruments' => $this->faker->numberBetween(0, 20),
            'market_share_pct' => $this->faker->randomFloat(2, 5, 40),
            'by_city' => [
                ['city' => 'Cuiabá', 'count' => $this->faker->numberBetween(10, 100)],
            ],
            'by_type' => [
                ['type' => 'Balança', 'count' => $this->faker->numberBetween(10, 200)],
            ],
        ];
    }
}
