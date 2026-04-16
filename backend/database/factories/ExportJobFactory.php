<?php

namespace Database\Factories;

use App\Models\ExportJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExportJobFactory extends Factory
{
    protected $model = ExportJob::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['customers', 'products', 'work_orders']),
            'status' => 'pending',
        ];
    }
}
