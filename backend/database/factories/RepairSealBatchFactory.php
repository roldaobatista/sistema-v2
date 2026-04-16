<?php

namespace Database\Factories;

use App\Models\InmetroSeal;
use App\Models\RepairSealBatch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepairSealBatchFactory extends Factory
{
    protected $model = RepairSealBatch::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(10, 100);

        return [
            'tenant_id' => Tenant::factory(),
            'type' => fake()->randomElement([InmetroSeal::TYPE_LACRE, InmetroSeal::TYPE_SELO_REPARO]),
            'batch_code' => fake()->unique()->numerify('LOTE-####'),
            'range_start' => '000001',
            'range_end' => str_pad($quantity, 6, '0', STR_PAD_LEFT),
            'quantity' => $quantity,
            'quantity_available' => $quantity,
            'received_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'received_by' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
        ];
    }

    public function seloReparo(): static
    {
        return $this->state(['type' => InmetroSeal::TYPE_SELO_REPARO]);
    }

    public function lacre(): static
    {
        return $this->state(['type' => InmetroSeal::TYPE_LACRE]);
    }

    public function fullyUsed(): static
    {
        return $this->state(['quantity_available' => 0]);
    }

    public function withPrefix(string $prefix): static
    {
        return $this->state(['prefix' => $prefix]);
    }
}
