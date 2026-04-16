<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BatchFactory extends Factory
{
    protected $model = Batch::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'code' => $this->faker->unique()->bothify('L-####-????'),
            'expires_at' => $this->faker->dateTimeBetween('now', '+2 years'),
            'cost_price' => $this->faker->randomFloat(2, 10, 500),
        ];
    }
}
