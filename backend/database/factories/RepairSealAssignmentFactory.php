<?php

namespace Database\Factories;

use App\Models\InmetroSeal;
use App\Models\RepairSealAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepairSealAssignmentFactory extends Factory
{
    protected $model = RepairSealAssignment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'seal_id' => fn (array $attributes) => InmetroSeal::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'technician_id' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'assigned_by' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'action' => RepairSealAssignment::ACTION_ASSIGNED,
        ];
    }

    public function assigned(): static
    {
        return $this->state(['action' => RepairSealAssignment::ACTION_ASSIGNED]);
    }

    public function returned(): static
    {
        return $this->state([
            'action' => RepairSealAssignment::ACTION_RETURNED,
            'notes' => fake()->sentence(),
        ]);
    }

    public function transferred(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => RepairSealAssignment::ACTION_TRANSFERRED,
            'previous_technician_id' => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
        ]);
    }
}
