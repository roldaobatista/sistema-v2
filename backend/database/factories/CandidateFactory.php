<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'job_posting_id' => JobPosting::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'resume_path' => null,
            'stage' => 'applied',
            'notes' => null,
            'rating' => null,
            'rejected_reason' => null,
        ];
    }

    public function screening(): static
    {
        return $this->state(fn () => ['stage' => 'screening']);
    }

    public function interview(): static
    {
        return $this->state(fn () => ['stage' => 'interview']);
    }

    public function technicalTest(): static
    {
        return $this->state(fn () => ['stage' => 'technical_test']);
    }

    public function offer(): static
    {
        return $this->state(fn () => ['stage' => 'offer']);
    }

    public function hired(): static
    {
        return $this->state(fn () => ['stage' => 'hired']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'stage' => 'rejected',
            'rejected_reason' => fake()->sentence(),
        ]);
    }

    public function withRating(int $rating = 4): static
    {
        return $this->state(fn () => ['rating' => $rating]);
    }
}
