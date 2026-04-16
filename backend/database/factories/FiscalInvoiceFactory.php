<?php

namespace Database\Factories;

use App\Models\FiscalInvoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiscalInvoiceFactory extends Factory
{
    protected $model = FiscalInvoice::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'number' => 'NF-'.fake()->unique()->numerify('#####'),
            'series' => '1',
            'type' => 'nfse',
            'customer_id' => null,
            'work_order_id' => null,
            'total' => fake()->randomFloat(2, 100, 50000),
            'status' => 'pending',
            'issued_at' => null,
        ];
    }
}
