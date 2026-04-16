<?php

namespace Database\Factories;

use App\Models\Skill;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->jobTitle(),
            'category' => $this->faker->randomElement(['soft', 'hard', 'language']),
            'description' => $this->faker->sentence(),
        ];
    }
}
