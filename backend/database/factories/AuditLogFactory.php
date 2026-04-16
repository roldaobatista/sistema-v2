<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'action' => 'updated',
            'auditable_type' => 'App\\Models\\Customer',
            'auditable_id' => 1,
            'description' => fake()->sentence(),
            'old_values' => [],
            'new_values' => [],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
