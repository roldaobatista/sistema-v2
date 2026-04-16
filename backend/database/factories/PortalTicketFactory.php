<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\PortalTicket;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PortalTicketFactory extends Factory
{
    protected $model = PortalTicket::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => 'open',
            'priority' => 'medium',
        ];
    }
}
