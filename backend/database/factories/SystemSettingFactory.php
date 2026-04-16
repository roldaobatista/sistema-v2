<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => fake()->unique()->slug(2),
            'value' => fake()->word(),
            'group' => fake()->randomElement(['general', 'financial', 'notification', 'os']),
        ];
    }
}
