<?php

namespace Database\Factories;

use App\Models\OvernightStay;
use App\Models\Tenant;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvernightStay>
 */
class OvernightStayFactory extends Factory
{
    protected $model = OvernightStay::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'travel_request_id' => TravelRequest::factory(),
            'user_id' => User::factory(),
            'stay_date' => $this->faker->dateTimeBetween('+1 day', '+10 days')->format('Y-m-d'),
            'hotel_name' => $this->faker->company().' Hotel',
            'city' => $this->faker->city(),
            'state' => $this->faker->randomElement(['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO']),
            'cost' => $this->faker->randomFloat(2, 80, 500),
            'status' => 'pending',
        ];
    }
}
