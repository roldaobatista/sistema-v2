<?php

namespace Database\Factories;

use App\Models\AccreditationScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccreditationScope>
 */
class AccreditationScopeFactory extends Factory
{
    protected $model = AccreditationScope::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'accreditation_number' => 'CRL-'.$this->faker->numerify('####'),
            'accrediting_body' => 'Cgcre/Inmetro',
            'scope_description' => 'Calibração de instrumentos de pesagem não automáticos',
            'equipment_categories' => ['Balancas Comerciais', 'Balancas Industriais', 'Balancas Analiticas e de Precisao'],
            'valid_from' => now()->subYear(),
            'valid_until' => now()->addYear(),
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'valid_from' => now()->subYears(2),
            'valid_until' => now()->subMonth(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
