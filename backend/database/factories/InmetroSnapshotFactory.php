<?php

namespace Database\Factories;

use App\Models\InmetroCompetitor;
use App\Models\InmetroSnapshot;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InmetroSnapshotFactory extends Factory
{
    protected $model = InmetroSnapshot::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'competitor_id' => InmetroCompetitor::factory(),
            'snapshot_type' => 'monthly',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'data' => [
                'total_instruments' => fake()->numberBetween(50, 500),
                'repair_count' => fake()->numberBetween(1, 100),
                'by_city' => ['Cuiaba' => fake()->numberBetween(1, 50)],
                'by_type' => ['Balanca' => fake()->numberBetween(1, 50)],
            ],
        ];
    }
}
