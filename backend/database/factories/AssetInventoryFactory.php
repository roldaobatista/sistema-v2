<?php

namespace Database\Factories;

use App\Models\AssetInventory;
use App\Models\AssetRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetInventory>
 */
class AssetInventoryFactory extends Factory
{
    protected $model = AssetInventory::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'asset_record_id' => AssetRecord::factory(),
            'inventory_date' => fake()->date(),
            'counted_location' => fake()->city(),
            'counted_status' => fake()->randomElement(['active', 'suspended', 'disposed', 'fully_depreciated']),
            'condition_ok' => fake()->boolean(85),
            'divergent' => fake()->boolean(20),
            'offline_reference' => null,
            'synced_from_pwa' => false,
            'notes' => fake()->sentence(),
            'counted_by' => User::factory(),
        ];
    }
}
