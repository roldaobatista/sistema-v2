<?php

namespace Database\Factories;

use App\Models\CommissionDispute;
use App\Models\CommissionEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionDisputeFactory extends Factory
{
    protected $model = CommissionDispute::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'commission_event_id' => CommissionEvent::factory(),
            'user_id' => User::factory(),
            'status' => CommissionDispute::STATUS_OPEN,
            'reason' => fake()->sentence(),
        ];
    }
}
