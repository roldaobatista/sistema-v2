<?php

namespace Database\Factories;

use App\Models\CrmDeal;
use App\Models\CrmDealProduct;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrmDealProductFactory extends Factory
{
    protected $model = CrmDealProduct::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'deal_id' => CrmDeal::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price' => fake()->randomFloat(2, 50, 5000),
        ];
    }
}
