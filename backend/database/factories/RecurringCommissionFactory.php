<?php

namespace Database\Factories;

use App\Models\CommissionRule;
use App\Models\RecurringCommission;
use App\Models\RecurringContract;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringCommissionFactory extends Factory
{
    protected $model = RecurringCommission::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'recurring_contract_id' => RecurringContract::factory(),
            'commission_rule_id' => CommissionRule::factory(),
            'status' => RecurringCommission::STATUS_ACTIVE,
        ];
    }
}
