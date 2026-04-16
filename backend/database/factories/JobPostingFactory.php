<?php

namespace Database\Factories;

use App\Models\JobPosting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobPostingFactory extends Factory
{
    protected $model = JobPosting::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->jobTitle(),
            'department_id' => null,
            'position_id' => null,
            'description' => fake()->paragraphs(2, true),
            'requirements' => fake()->paragraphs(1, true),
            'salary_range_min' => fake()->randomFloat(2, 2000, 5000),
            'salary_range_max' => fake()->randomFloat(2, 5001, 15000),
            'status' => 'open',
            'opened_at' => now(),
            'closed_at' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function onHold(): static
    {
        return $this->state(fn () => [
            'status' => 'on_hold',
        ]);
    }
}
