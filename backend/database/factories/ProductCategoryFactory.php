<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
