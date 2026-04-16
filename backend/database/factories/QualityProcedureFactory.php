<?php

namespace Database\Factories;

use App\Models\QualityProcedure;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityProcedure>
 */
class QualityProcedureFactory extends Factory
{
    protected $model = QualityProcedure::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => strtoupper(fake()->bothify('QP-###')),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'revision' => 1,
            'category' => 'general',
            'status' => 'draft',
            'content' => fake()->paragraph(),
        ];
    }
}
