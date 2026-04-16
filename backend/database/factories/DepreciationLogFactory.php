<?php

namespace Database\Factories;

use App\Models\AssetRecord;
use App\Models\DepreciationLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepreciationLog>
 */
class DepreciationLogFactory extends Factory
{
    protected $model = DepreciationLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'asset_record_id' => AssetRecord::factory(),
            'reference_month' => fake()->dateTimeBetween('-12 months', 'now')->format('Y-m-01'),
            'depreciation_amount' => fake()->randomFloat(2, 50, 1000),
            'accumulated_before' => fake()->randomFloat(2, 0, 1000),
            'accumulated_after' => fake()->randomFloat(2, 1000, 5000),
            'book_value_after' => fake()->randomFloat(2, 0, 10000),
            'method_used' => fake()->randomElement(['linear', 'accelerated', 'units_produced']),
            'generated_by' => fake()->randomElement(['automatic_job', 'manual']),
        ];
    }
}
