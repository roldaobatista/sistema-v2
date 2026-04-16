<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitCheckin;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitCheckinFactory extends Factory
{
    protected $model = VisitCheckin::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'customer_id' => Customer::factory(),
            'checked_in_at' => now(),
            'latitude' => fake()->latitude(-15.8, -15.5),
            'longitude' => fake()->longitude(-56.2, -55.9),
            'status' => 'checked_in',
        ];
    }
}
