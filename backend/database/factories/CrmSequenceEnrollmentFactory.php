<?php

namespace Database\Factories;

use App\Models\CrmSequence;
use App\Models\CrmSequenceEnrollment;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrmSequenceEnrollmentFactory extends Factory
{
    protected $model = CrmSequenceEnrollment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sequence_id' => CrmSequence::factory(),
            'customer_id' => Customer::factory(),
            'status' => 'active',
            'current_step' => 0,
        ];
    }
}
