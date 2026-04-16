<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractAddendumFactory extends Factory
{
    protected $model = ContractAddendum::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contract_id' => Contract::factory(),
            'type' => 'value_change',
            'description' => $this->faker->text(),
            'new_value' => $this->faker->randomFloat(2, 1000, 50000),
            'new_end_date' => clone $this->faker->dateTimeBetween('+1 year', '+2 years'),
            'effective_date' => $this->faker->date(),
            'status' => 'pending',
            'created_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
