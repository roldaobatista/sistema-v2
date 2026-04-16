<?php

namespace Database\Factories;

use App\Models\AssetRecord;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetRecord>
 */
class AssetRecordFactory extends Factory
{
    protected $model = AssetRecord::class;

    public function definition(): array
    {
        $acquisitionValue = fake()->randomFloat(2, 1000, 50000);
        $residualValue = fake()->randomFloat(2, 0, $acquisitionValue * 0.2);
        $lifeMonths = fake()->numberBetween(12, 120);

        return [
            'tenant_id' => Tenant::factory(),
            'code' => 'AT-'.fake()->unique()->numerify('#####'),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['machinery', 'vehicle', 'equipment', 'furniture', 'it', 'tooling', 'other']),
            'acquisition_date' => fake()->dateTimeBetween('-3 years', '-1 month'),
            'acquisition_value' => $acquisitionValue,
            'residual_value' => $residualValue,
            'useful_life_months' => $lifeMonths,
            'depreciation_method' => fake()->randomElement(['linear', 'accelerated', 'units_produced']),
            'depreciation_rate' => AssetRecord::calculateDepreciationRate('linear', $lifeMonths),
            'accumulated_depreciation' => 0,
            'current_book_value' => $acquisitionValue,
            'status' => AssetRecord::STATUS_ACTIVE,
            'location' => fake()->city(),
            'ciap_credit_type' => 'none',
            'ciap_total_installments' => null,
            'ciap_installments_taken' => 0,
        ];
    }
}
