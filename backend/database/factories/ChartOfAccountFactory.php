<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChartOfAccountFactory extends Factory
{
    protected $model = ChartOfAccount::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => fake()->unique()->numerify('##.##.###'),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['revenue', 'expense', 'asset', 'liability']),
            'is_system' => false,
            'is_active' => true,
        ];
    }

    public function revenue(): static
    {
        return $this->state(fn () => ['type' => 'revenue']);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => 'expense']);
    }
}
