<?php

namespace Database\Factories;

use App\Models\AccountPayable;
use App\Models\AccountPayableInstallment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountPayableInstallmentFactory extends Factory
{
    protected $model = AccountPayableInstallment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'account_payable_id' => AccountPayable::factory(),
            'installment_number' => fake()->numberBetween(1, 12),
            'due_date' => fake()->dateTimeBetween('now', '+1 year'),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'paid_amount' => 0,
            'status' => 'pending',
        ];
    }
}
