<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseQuotation;
use App\Models\PurchaseQuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseQuotationItem>
 */
class PurchaseQuotationItemFactory extends Factory
{
    protected $model = PurchaseQuotationItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 20);
        $unitPrice = fake()->randomFloat(2, 10, 500);

        return [
            'purchase_quotation_id' => PurchaseQuotation::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
        ];
    }
}
