<?php

namespace Database\Factories;

use App\Models\AssetMovement;
use App\Models\AssetRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetMovement>
 */
class AssetMovementFactory extends Factory
{
    protected $model = AssetMovement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'asset_record_id' => AssetRecord::factory(),
            'movement_type' => fake()->randomElement(['transfer', 'assignment', 'maintenance']),
            'from_location' => fake()->city(),
            'to_location' => fake()->city(),
            'from_responsible_user_id' => User::factory(),
            'to_responsible_user_id' => User::factory(),
            'moved_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
