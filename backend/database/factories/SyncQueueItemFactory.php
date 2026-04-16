<?php

namespace Database\Factories;

use App\Models\SyncQueueItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SyncQueueItemFactory extends Factory
{
    protected $model = SyncQueueItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'entity_type' => $this->faker->randomElement(['work_orders', 'customers', 'products']),
            'entity_id' => $this->faker->numberBetween(1, 1000),
            'action' => $this->faker->randomElement(SyncQueueItem::ACTIONS),
            'payload' => ['field' => 'value'],
            'status' => 'pending',
            'priority' => 0,
            'attempts' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error_message' => 'Connection timeout',
            'attempts' => 3,
        ]);
    }
}
