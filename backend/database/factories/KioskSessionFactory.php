<?php

namespace Database\Factories;

use App\Models\KioskSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class KioskSessionFactory extends Factory
{
    protected $model = KioskSession::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'device_identifier' => 'KIOSK-'.$this->faker->uuid(),
            'status' => 'active',
            'allowed_pages' => ['dashboard', 'work-orders'],
            'started_at' => now(),
            'last_activity_at' => now(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => 'KioskBrowser/1.0',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'ended_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'last_activity_at' => now()->subMinutes(30),
        ]);
    }
}
