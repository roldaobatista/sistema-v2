<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TimeClockAdjustment;
use App\Models\TimeClockEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimeClockAdjustmentFactory extends Factory
{
    protected $model = TimeClockAdjustment::class;

    public function definition(): array
    {
        $clockIn = now()->subHours(8);
        $clockOut = now();

        return [
            'tenant_id' => Tenant::factory(),
            'time_clock_entry_id' => TimeClockEntry::factory(),
            'requested_by' => User::factory(),
            'original_clock_in' => $clockIn,
            'original_clock_out' => $clockOut,
            'adjusted_clock_in' => $clockIn->copy()->subMinutes(15),
            'adjusted_clock_out' => $clockOut->copy()->addMinutes(15),
            'reason' => $this->faker->sentence(),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => User::factory(),
            'decided_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_by' => User::factory(),
            'rejection_reason' => $this->faker->sentence(),
            'decided_at' => now(),
        ]);
    }
}
