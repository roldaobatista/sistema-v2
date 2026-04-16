<?php

namespace Database\Factories;

use App\Models\ProjectResource;
use App\Models\ProjectTimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTimeEntry>
 */
class ProjectTimeEntryFactory extends Factory
{
    protected $model = ProjectTimeEntry::class;

    public function definition(): array
    {
        $resource = ProjectResource::factory()->create();

        return [
            'tenant_id' => $resource->tenant_id,
            'project_id' => $resource->project_id,
            'project_resource_id' => $resource->id,
            'milestone_id' => null,
            'work_order_id' => null,
            'date' => fake()->dateTimeBetween('-10 days', 'now'),
            'hours' => fake()->randomFloat(2, 0.5, 8),
            'description' => fake()->sentence(),
            'billable' => fake()->boolean(),
        ];
    }
}
