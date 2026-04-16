<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractMeasurement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractMeasurementFactory extends Factory
{
    protected $model = ContractMeasurement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contract_id' => Contract::factory(),
            'period' => $this->faker->date('Y-m'),
            'items' => [['name' => 'Service 1', 'price' => 500]],
            'total_accepted' => $this->faker->randomFloat(2, 100, 10000),
            'total_rejected' => 0.00,
            'notes' => $this->faker->sentence(),
            'status' => 'pending_approval',
            'created_by' => User::factory(),
        ];
    }
}
