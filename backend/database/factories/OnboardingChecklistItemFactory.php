<?php

namespace Database\Factories;

use App\Models\OnboardingChecklist;
use App\Models\OnboardingChecklistItem;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OnboardingChecklistItemFactory extends Factory
{
    protected $model = OnboardingChecklistItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'onboarding_checklist_id' => OnboardingChecklist::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'responsible_id' => null,
            'is_completed' => false,
            'order' => $this->faker->numberBetween(1, 20),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }
}
