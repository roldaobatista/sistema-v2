<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectResource>
 */
class ProjectResourceFactory extends Factory
{
    protected $model = ProjectResource::class;

    public function definition(): array
    {
        $project = Project::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $project->tenant_id,
            'current_tenant_id' => $project->tenant_id,
        ]);

        return [
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => fake()->jobTitle(),
            'allocation_percent' => fake()->randomFloat(2, 10, 100),
            'start_date' => fake()->dateTimeBetween('now', '+10 days'),
            'end_date' => fake()->dateTimeBetween('+11 days', '+40 days'),
            'hourly_rate' => fake()->randomFloat(2, 80, 250),
            'total_hours_planned' => fake()->randomFloat(2, 10, 200),
            'total_hours_logged' => 0,
        ];
    }
}
