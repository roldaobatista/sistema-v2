<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\VisitSurvey;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitSurveyFactory extends Factory
{
    protected $model = VisitSurvey::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional(0.5)->sentence(),
            'is_active' => true,
            'fields' => json_encode([
                ['name' => 'observacao', 'type' => 'text', 'required' => false],
            ]),
        ];
    }
}
