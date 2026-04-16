<?php

namespace Database\Factories;

use App\Models\AccountPayableCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountPayableCategoryFactory extends Factory
{
    protected $model = AccountPayableCategory::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
