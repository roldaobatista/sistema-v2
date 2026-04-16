<?php

namespace Database\Factories;

use App\Models\EscalationRule;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class EscalationRuleFactory extends Factory
{
    protected $model = EscalationRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sla_policy_id' => SlaPolicy::factory(),
            'name' => $this->faker->words(3, true),
            'trigger_minutes' => $this->faker->numberBetween(15, 120),
            'action_type' => 'notify',
            'action_payload' => ['type' => 'email', 'target' => 'manager'],
            'is_active' => true,
        ];
    }
}
