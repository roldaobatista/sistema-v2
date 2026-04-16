<?php

namespace Database\Factories;

use App\Models\SystemAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemAlertFactory extends Factory
{
    protected $model = SystemAlert::class;

    public function definition(): array
    {
        $types = array_keys(SystemAlert::TYPES);

        return [
            'tenant_id' => 1,
            'alert_type' => fake()->randomElement($types),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'title' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'status' => 'active',
            'channels_sent' => ['database'],
        ];
    }

    public function acknowledged(): static
    {
        return $this->state(fn () => [
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn () => [
            'status' => 'dismissed',
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn () => [
            'severity' => 'critical',
        ]);
    }

    public function high(): static
    {
        return $this->state(fn () => [
            'severity' => 'high',
        ]);
    }
}
