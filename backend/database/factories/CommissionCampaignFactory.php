<?php

namespace Database\Factories;

use App\Models\CommissionCampaign;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionCampaignFactory extends Factory
{
    protected $model = CommissionCampaign::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->sentence(3),
            'multiplier' => $this->faker->randomFloat(4, 1, 3),
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(3),
            'active' => true,
        ];
    }
}
