<?php

namespace Database\Factories;

use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\TicketCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketCategoryFactory extends Factory
{
    protected $model = TicketCategory::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sla_policy_id' => SlaPolicy::factory(),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'default_priority' => 'medium',
        ];
    }
}
