<?php

namespace Database\Factories;

use App\Models\OnboardingChecklist;
use App\Models\OnboardingTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OnboardingChecklistFactory extends Factory
{
    protected $model = OnboardingChecklist::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'onboarding_template_id' => OnboardingTemplate::factory(),
            'started_at' => now(),
            'completed_at' => null,
            'status' => 'in_progress',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
            'status' => 'completed',
        ]);
    }
}
