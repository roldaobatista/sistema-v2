<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMilestone>
 */
class ProjectMilestoneFactory extends Factory
{
    protected $model = ProjectMilestone::class;

    public function definition(): array
    {
        $project = Project::factory()->create();

        return [
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'name' => fake()->sentence(2),
            'status' => fake()->randomElement(['pending', 'in_progress', 'completed']),
            'order' => fake()->numberBetween(1, 5),
            'planned_start' => fake()->dateTimeBetween('now', '+15 days'),
            'planned_end' => fake()->dateTimeBetween('+16 days', '+30 days'),
            'billing_value' => fake()->randomFloat(2, 1000, 5000),
            'weight' => fake()->randomFloat(2, 1, 3),
            'dependencies' => null,
            'deliverables' => fake()->sentence(),
        ];
    }
}
