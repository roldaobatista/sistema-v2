<?php

namespace Database\Factories;

use App\Models\CrmSequence;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrmSequenceFactory extends Factory
{
    protected $model = CrmSequence::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->sentence(3),
            'status' => 'active',
        ];
    }
}
