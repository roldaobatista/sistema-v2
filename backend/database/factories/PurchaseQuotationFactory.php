<?php

namespace Database\Factories;

use App\Models\PurchaseQuotation;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseQuotationFactory extends Factory
{
    protected $model = PurchaseQuotation::class;

    public function definition()
    {
        return [
            'tenant_id' => Tenant::factory(),
            'reference' => 'PQ-'.$this->faker->unique()->randomNumber(5),
            'supplier_id' => Supplier::factory(),
            'status' => 'draft',
            'total' => $this->faker->randomFloat(2, 100, 10000),
        ];
    }
}
