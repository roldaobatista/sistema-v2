<?php

namespace Database\Factories;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => User::factory(),
            'description' => fake()->sentence(4),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'expense_date' => fake()->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d'),
            'affects_net_value' => true,
            'affects_technician_cash' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Expense $expense) {
            if (! $expense->wasRecentlyCreated) {
                return;
            }
            if ($expense->status === null) {
                $expense->forceFill(['status' => ExpenseStatus::PENDING])->saveQuietly();
            }
        });
    }

    public function approved(): static
    {
        return $this->afterCreating(function (Expense $expense) {
            $expense->forceFill(['status' => ExpenseStatus::APPROVED])->saveQuietly();
        });
    }

    public function reviewed(): static
    {
        return $this->afterCreating(function (Expense $expense) {
            $expense->forceFill(['status' => ExpenseStatus::REVIEWED])->saveQuietly();
        });
    }

    public function rejected(): static
    {
        return $this->afterCreating(function (Expense $expense) {
            $expense->forceFill([
                'status' => ExpenseStatus::REJECTED,
                'rejection_reason' => fake()->sentence(),
            ])->saveQuietly();
        });
    }

    public function reimbursed(): static
    {
        return $this->afterCreating(function (Expense $expense) {
            $expense->forceFill(['status' => ExpenseStatus::REIMBURSED])->saveQuietly();
        });
    }

    public function withTechnicianCash(): static
    {
        return $this->state(fn () => ['affects_technician_cash' => true]);
    }
}
