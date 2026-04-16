<?php

namespace Database\Factories;

use App\Models\AlertConfiguration;
use App\Models\SystemAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlertConfigurationFactory extends Factory
{
    protected $model = AlertConfiguration::class;

    public function definition(): array
    {
        $types = array_keys(SystemAlert::TYPES);

        return [
            'tenant_id' => 1,
            'alert_type' => fake()->unique()->randomElement($types),
            'is_enabled' => true,
            'channels' => ['database', 'email'],
            'days_before' => fake()->numberBetween(3, 30),
            'recipients' => [1],
            'escalation_hours' => fake()->numberBetween(1, 48),
            'escalation_recipients' => [1],
        ];
    }
}
