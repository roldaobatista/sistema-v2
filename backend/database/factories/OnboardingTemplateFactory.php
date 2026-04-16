<?php

namespace Database\Factories;

use App\Models\OnboardingTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OnboardingTemplateFactory extends Factory
{
    protected $model = OnboardingTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true).' Template',
            'type' => $this->faker->randomElement(['onboarding', 'offboarding']),
            'default_tasks' => [],
            'is_active' => true,
        ];
    }
}
