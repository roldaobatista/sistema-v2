<?php

namespace Database\Factories;

use App\Models\PortalTicket;
use App\Models\SlaPolicy;
use App\Models\SlaViolation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SlaViolationFactory extends Factory
{
    protected $model = SlaViolation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'portal_ticket_id' => PortalTicket::factory(),
            'sla_policy_id' => SlaPolicy::factory(),
            'violation_type' => 'response_time',
            'violated_at' => now()->subHours(2),
            'minutes_exceeded' => 30,
        ];
    }
}
