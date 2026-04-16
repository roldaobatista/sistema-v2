<?php

namespace Database\Factories;

use App\Models\InmetroLeadInteraction;
use App\Models\InmetroOwner;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InmetroLeadInteractionFactory extends Factory
{
    protected $model = InmetroLeadInteraction::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'owner_id' => InmetroOwner::factory(),
            'user_id' => User::factory(),
            'channel' => $this->faker->randomElement(['phone', 'whatsapp', 'email', 'visit']),
            'result' => $this->faker->randomElement(['interested', 'not_interested', 'no_answer', 'callback', 'converted']),
            'notes' => $this->faker->sentence(),
            'next_follow_up_at' => null,
        ];
    }
}
