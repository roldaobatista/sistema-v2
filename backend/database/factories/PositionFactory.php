<?php

namespace Database\Factories;

use App\Models\Position;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->jobTitle(),
            'department_id' => null,
        ];
    }
}
