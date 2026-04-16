<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
        ];
    }
}
