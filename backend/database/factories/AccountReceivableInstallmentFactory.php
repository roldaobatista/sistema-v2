<?php

namespace Database\Factories;

use App\Models\AccountReceivable;
use App\Models\AccountReceivableInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountReceivableInstallment>
 */
class AccountReceivableInstallmentFactory extends Factory
{
    protected $model = AccountReceivableInstallment::class;

    public function definition(): array
    {
        return [
            'account_receivable_id' => AccountReceivable::factory(),
            'tenant_id' => function (array $attrs): ?int {
                /** @var AccountReceivable|null $receivable */
                $receivable = AccountReceivable::find($attrs['account_receivable_id']);

                return $receivable?->tenant_id;
            },
            'installment_number' => 1,
            'due_date' => now()->addDays(10),
            'amount' => $this->faker->randomFloat(2, 50, 5000),
            'status' => 'pending',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'paid_at' => now(),
            'paid_amount' => fn (array $attrs) => $attrs['amount'],
        ]);
    }
}
