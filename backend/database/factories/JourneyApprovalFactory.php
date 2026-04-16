<?php

namespace Database\Factories;

use App\Models\JourneyApproval;
use App\Models\JourneyDay;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JourneyApproval>
 */
class JourneyApprovalFactory extends Factory
{
    protected $model = JourneyApproval::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'journey_day_id' => JourneyDay::factory(),
            'level' => $this->faker->randomElement(['operational', 'hr']),
            'status' => 'pending',
        ];
    }

    public function operational(): static
    {
        return $this->state(fn () => ['level' => 'operational']);
    }

    public function hr(): static
    {
        return $this->state(fn () => ['level' => 'hr']);
    }

    public function approved(int $approverId): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approver_id' => $approverId,
            'decided_at' => now(),
        ]);
    }

    public function rejected(int $approverId, string $notes = 'Rejeitado'): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'approver_id' => $approverId,
            'decided_at' => now(),
            'notes' => $notes,
        ]);
    }
}
