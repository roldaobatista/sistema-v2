<?php

namespace Database\Factories;

use App\Models\AccountReceivable;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'payable_type' => AccountReceivable::class,
            'payable_id' => AccountReceivable::factory(),
            'received_by' => User::factory(),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'payment_method' => fake()->randomElement(['pix', 'boleto', 'dinheiro', 'cartao_credito', 'transferencia']),
            'payment_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}
