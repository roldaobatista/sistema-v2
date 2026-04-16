<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnalyticsDataset;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsDataset>
 */
class AnalyticsDatasetFactory extends Factory
{
    protected $model = AnalyticsDataset::class;

    public function definition(): array
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        return [
            'tenant_id' => $tenant->id,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'source_modules' => ['work_orders'],
            'query_definition' => [
                'source' => 'work_orders',
                'columns' => ['id', 'status', 'created_at'],
                'order_by' => [['column' => 'created_at', 'direction' => 'desc']],
            ],
            'refresh_strategy' => 'manual',
            'cache_ttl_minutes' => 1440,
            'last_refreshed_at' => null,
            'is_active' => true,
            'created_by' => $user->id,
        ];
    }
}
