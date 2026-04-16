<?php

namespace Database\Factories;

use App\Models\Survey;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyFactory extends Factory
{
    protected $model = Survey::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => 'draft',
            'is_active' => true,
        ];
    }
}
