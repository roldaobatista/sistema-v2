<?php

namespace Database\Factories;

use App\Models\AgendaTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgendaTemplateFactory extends Factory
{
    protected $model = AgendaTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nome' => fake()->words(3, true),
            'descricao' => fake()->sentence(),
            'ativo' => true,
            'created_by' => User::factory(),
        ];
    }
}
