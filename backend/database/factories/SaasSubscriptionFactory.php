<?php

namespace Database\Factories;

use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaasSubscription>
 */
class SaasSubscriptionFactory extends Factory
{
    protected $model = SaasSubscription::class;

    public function definition(): array
    {
        $plan = SaasPlan::factory();
        $startDate = $this->faker->dateTimeBetween('-6 months', 'now');

        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => $plan,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'price' => $this->faker->randomFloat(2, 99, 999),
            'discount' => 0,
            'started_at' => $startDate,
            'trial_ends_at' => null,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ];
    }

    public function trial(): static
    {
        return $this->state(fn () => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'User requested',
        ]);
    }
}
