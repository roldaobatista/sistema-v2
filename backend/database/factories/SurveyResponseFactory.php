<?php

namespace Database\Factories;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyResponseFactory extends Factory
{
    protected $model = SurveyResponse::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'survey_id' => Survey::factory(),
            'respondent_id' => User::factory(),
            'answers' => ['q1' => 'yes', 'q2' => 'no'],
            'score' => fake()->randomFloat(2, 0, 10),
            'completed_at' => now(),
        ];
    }
}
