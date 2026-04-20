<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'email_verified_at' => now(),
            // NÃO usar Hash::make — o cast 'hashed' do Model já faz o hash.
            'password' => 'password',
            'remember_token' => Str::random(10),
            // SEC-08: `is_active`, `tenant_id`, `current_tenant_id` saíram do
            // $fillable do User (mass-assignment proibido). Em factory, esses
            // campos são atribuídos via `afterMaking`/`forceFill` em cada
            // instance, porque factory mass-assign através do constructor
            // respeitaria $fillable e silenciaria os atributos.
            'last_login_at' => null,
        ];
    }

    /**
     * Hook de factory: força atributos fora de `$fillable` (is_active,
     * tenant_id, current_tenant_id) — caminho explícito controlado pelo
     * desenvolvedor, equivalente a um path administrativo legítimo.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            // Defaults coerentes com testes historicamente baseados em is_active=true
            // e tenant_id/current_tenant_id = null (quando o chamador não informar).
            if ($user->getAttribute('is_active') === null) {
                $user->forceFill(['is_active' => true]);
            }
        });
    }

    /**
     * Permite `User::factory()->create(['tenant_id' => X, 'current_tenant_id' => Y, 'is_active' => false])`
     * continuar funcionando mesmo com esses campos fora de $fillable —
     * o método create() do factory aplica o array via forceFill explícito.
     *
     * @param  array<string, mixed>  $attributes
     * @param  Model|null  $parent
     */
    public function newModel(array $attributes = [])
    {
        $model = parent::newModel([]);

        // forceFill ignora $fillable — é o único caminho para popular
        // is_active/tenant_id/current_tenant_id em factories/testes após SEC-08.
        if (! empty($attributes)) {
            $model->forceFill($attributes);
        }

        return $model;
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
