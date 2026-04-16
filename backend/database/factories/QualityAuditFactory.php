<?php

namespace Database\Factories;

use App\Models\QualityAudit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QualityAuditFactory extends Factory
{
    protected $model = QualityAudit::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'audit_number' => 'AUD-'.fake()->unique()->numerify('####'),
            'title' => fake()->sentence(),
            'summary' => fake()->paragraph(),
            'planned_date' => fake()->date(),
            'status' => 'pending',
            'auditor_id' => User::factory(),
        ];
    }
}
