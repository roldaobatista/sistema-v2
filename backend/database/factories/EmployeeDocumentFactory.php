<?php

namespace Database\Factories;

use App\Models\EmployeeDocument;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'category' => $this->faker->randomElement(['aso', 'nr', 'contract', 'license', 'certification', 'id_doc']),
            'name' => $this->faker->words(3, true),
            'file_path' => 'documents/'.$this->faker->uuid().'.pdf',
            'expiry_date' => $this->faker->optional()->dateTimeBetween('+1 month', '+2 years'),
            'issued_date' => $this->faker->optional()->dateTimeBetween('-2 years', 'now'),
            'issuer' => $this->faker->optional()->company(),
            'is_mandatory' => $this->faker->boolean(30),
            'status' => 'valid',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => now()->subDays(30),
            'status' => 'expired',
        ]);
    }

    public function mandatory(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_mandatory' => true,
        ]);
    }
}
