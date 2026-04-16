<?php

namespace Database\Factories;

use App\Models\EmailLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailLogFactory extends Factory
{
    protected $model = EmailLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'to' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'status' => 'sent',
            'sent_at' => now(),
        ];
    }
}
