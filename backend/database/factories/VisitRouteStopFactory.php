<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\VisitRoute;
use App\Models\VisitRouteStop;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitRouteStopFactory extends Factory
{
    protected $model = VisitRouteStop::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'visit_route_id' => VisitRoute::factory(),
            'customer_id' => Customer::factory(),
            'order' => fake()->numberBetween(1, 20),
            'status' => 'pending',
        ];
    }
}
