<?php

namespace Database\Factories;

use App\Models\TechnicianCertification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnicianCertification>
 */
class TechnicianCertificationFactory extends Factory
{
    protected $model = TechnicianCertification::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['cnh', 'nr10', 'nr35', 'aso', 'treinamento']),
            'name' => $this->faker->words(3, true),
            'number' => $this->faker->numerify('CERT-#####'),
            'issued_at' => $this->faker->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'),
            'expires_at' => $this->faker->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'issuer' => $this->faker->company(),
            'status' => 'valid',
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDays(10)->format('Y-m-d'),
            'status' => 'expired',
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->addDays(15)->format('Y-m-d'),
            'status' => 'expiring_soon',
        ]);
    }

    public function cnh(): static
    {
        return $this->state(fn () => [
            'type' => 'cnh',
            'name' => 'CNH',
        ]);
    }

    public function nr10(): static
    {
        return $this->state(fn () => [
            'type' => 'nr10',
            'name' => 'NR-10 Segurança em Instalações Elétricas',
        ]);
    }

    public function nr35(): static
    {
        return $this->state(fn () => [
            'type' => 'nr35',
            'name' => 'NR-35 Trabalho em Altura',
        ]);
    }
}
