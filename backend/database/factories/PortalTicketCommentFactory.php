<?php

namespace Database\Factories;

use App\Models\PortalTicket;
use App\Models\PortalTicketComment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PortalTicketCommentFactory extends Factory
{
    protected $model = PortalTicketComment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'portal_ticket_id' => PortalTicket::factory(),
            'user_id' => User::factory(),
            'content' => $this->faker->paragraph(),
            'is_internal' => false,
        ];
    }
}
