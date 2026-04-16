<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->randomElement(['Dinheiro', 'Cartão de Crédito', 'Cartão de Débito', 'Boleto', 'Pix', 'Transferência']),
            'code' => strtoupper($this->faker->unique()->lexify('PM_???')),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }
}
