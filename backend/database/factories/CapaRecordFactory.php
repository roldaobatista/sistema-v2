<?php

namespace Database\Factories;

use App\Models\CapaRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CapaRecordFactory extends Factory
{
    protected $model = CapaRecord::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'type' => fake()->randomElement(['corrective', 'preventive']),
            'status' => 'open',
            'description' => fake()->paragraph(),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
        ];
    }
}
