<?php

namespace Database\Factories;

use App\Models\QuoteTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuoteTemplateFactory extends Factory
{
    protected $model = QuoteTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'is_active' => true,
        ];
    }
}
