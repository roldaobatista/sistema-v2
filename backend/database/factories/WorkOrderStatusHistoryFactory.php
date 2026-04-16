<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderStatusHistoryFactory extends Factory
{
    protected $model = WorkOrderStatusHistory::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_order_id' => WorkOrder::factory(),
            'from_status' => WorkOrder::STATUS_OPEN,
            'to_status' => WorkOrder::STATUS_IN_DISPLACEMENT,
            'user_id' => User::factory(),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}
