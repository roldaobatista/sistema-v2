<?php

namespace Database\Factories;

use App\Models\CrmDeal;
use App\Models\CrmFollowUpTask;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrmFollowUpTaskFactory extends Factory
{
    protected $model = CrmFollowUpTask::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'deal_id' => CrmDeal::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'due_at' => fake()->dateTimeBetween('now', '+30 days'),
            'status' => 'pending',
        ];
    }
}
