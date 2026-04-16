<?php

namespace Database\Factories;

use App\Models\CltViolation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CltViolationFactory extends Factory
{
    protected $model = CltViolation::class;

    public function definition(): array
    {
        $types = ['overtime_limit_exceeded', 'intra_shift_short', 'intra_shift_missing', 'inter_shift_short', 'dsr_missing'];
        $severities = ['medium', 'high', 'critical'];

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'date' => $this->faker->date(),
            'violation_type' => $this->faker->randomElement($types),
            'severity' => $this->faker->randomElement($severities),
            'description' => $this->faker->sentence(),
            'resolved' => false,
            'metadata' => [],
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => User::factory(),
        ]);
    }
}
