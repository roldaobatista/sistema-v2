<?php

namespace Database\Factories;

use App\Models\AssetDisposal;
use App\Models\AssetRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetDisposal>
 */
class AssetDisposalFactory extends Factory
{
    protected $model = AssetDisposal::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'asset_record_id' => AssetRecord::factory(),
            'disposal_date' => now()->toDateString(),
            'reason' => fake()->randomElement(['sale', 'loss', 'scrap', 'donation', 'theft']),
            'disposal_value' => fake()->randomFloat(2, 0, 10000),
            'book_value_at_disposal' => fake()->randomFloat(2, 0, 10000),
            'gain_loss' => fake()->randomFloat(2, -1000, 1000),
            'approved_by' => User::factory(),
            'created_by' => User::factory(),
        ];
    }
}
