<?php

namespace Database\Factories;

use App\Models\NonConformity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NonConformityFactory extends Factory
{
    protected $model = NonConformity::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nc_number' => 'NC-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'source' => $this->faker->randomElement(array_keys(NonConformity::SOURCES)),
            'severity' => $this->faker->randomElement(array_keys(NonConformity::SEVERITIES)),
            'status' => 'open',
            'reported_by' => User::factory(),
            'assigned_to' => null,
            'due_date' => $this->faker->optional()->dateTimeBetween('now', '+60 days'),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'closed_at' => now(),
            'root_cause' => $this->faker->sentence(),
            'corrective_action' => $this->faker->sentence(),
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn () => ['severity' => 'critical']);
    }

    public function fromAudit(): static
    {
        return $this->state(fn () => ['source' => 'audit']);
    }
}
