<?php

namespace Database\Factories;

use App\Models\FiscalInvoice;
use App\Models\FiscalInvoiceItem;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiscalInvoiceItemFactory extends Factory
{
    protected $model = FiscalInvoiceItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'fiscal_invoice_id' => FiscalInvoice::factory(),
            'description' => fake()->sentence(),
            'quantity' => fake()->randomFloat(2, 1, 10),
            'unit_price' => fake()->randomFloat(2, 10, 1000),
            'total' => fake()->randomFloat(2, 10, 10000),
        ];
    }
}
