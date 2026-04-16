<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'street' => fake()->streetName(),
            'number' => (string) fake()->buildingNumber(),
            'city' => fake()->city(),
            'state' => fake()->lexify('??'),
            'zip' => fake()->postcode(),
            'is_main' => true,
        ];
    }
}
