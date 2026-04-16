<?php

namespace Database\Factories;

use App\Models\AutoAssignmentRule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutoAssignmentRule>
 */
class AutoAssignmentRuleFactory extends Factory
{
    protected $model = AutoAssignmentRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->sentence(3),
            'entity_type' => 'work_order',
            'strategy' => fake()->randomElement(['round_robin', 'least_loaded', 'skill_match', 'proximity']),
            'conditions' => ['type' => 'distance', 'max' => 50],
            'technician_ids' => [],
            'required_skills' => [],
            'priority' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
