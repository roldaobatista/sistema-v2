<?php

namespace Database\Factories;

use App\Models\Payroll;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'reference_month' => now()->format('Y-m'),
            'type' => 'regular',
            'status' => 'draft',
        ];
    }
}
