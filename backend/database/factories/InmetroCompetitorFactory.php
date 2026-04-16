<?php

namespace Database\Factories;

use App\Models\InmetroCompetitor;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InmetroCompetitorFactory extends Factory
{
    protected $model = InmetroCompetitor::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'cnpj' => fake()->numerify('##.###.###/####-##'),
            'authorization_number' => fake()->numerify('AUTH-####'),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'address' => fake()->address(),
            'authorized_species' => ['balanca_rodoviaria', 'balanca_comercial'],
            'mechanics' => ['mecanica', 'eletronica'],
            'accuracy_classes' => ['III', 'IIII'],
            'authorization_valid_until' => fake()->dateTimeBetween('+1 month', '+2 years'),
            'total_repairs_done' => fake()->numberBetween(0, 500),
        ];
    }
}
