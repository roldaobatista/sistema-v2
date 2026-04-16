<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitRouteFactory extends Factory
{
    protected $model = VisitRoute::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'name' => 'Rota '.fake()->city(),
            'status' => 'active',
            'scheduled_date' => fake()->dateTimeBetween('now', '+7 days')->format('Y-m-d'),
        ];
    }
}
