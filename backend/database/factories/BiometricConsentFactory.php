<?php

namespace Database\Factories;

use App\Models\BiometricConsent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiometricConsent>
 */
class BiometricConsentFactory extends Factory
{
    protected $model = BiometricConsent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'data_type' => $this->faker->randomElement(['geolocation', 'facial', 'fingerprint']),
            'legal_basis' => 'consent',
            'purpose' => 'Registro de ponto com biometria facial para controle de jornada',
            'consented_at' => now()->format('Y-m-d'),
            'retention_days' => 365,
            'is_active' => true,
        ];
    }

    public function geolocation(): static
    {
        return $this->state(fn () => [
            'data_type' => 'geolocation',
            'purpose' => 'Registro de geolocalização para controle de deslocamento',
        ]);
    }

    public function facial(): static
    {
        return $this->state(fn () => [
            'data_type' => 'facial',
            'purpose' => 'Reconhecimento facial para registro de ponto',
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now()->format('Y-m-d'),
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay()->format('Y-m-d'),
        ]);
    }
}
