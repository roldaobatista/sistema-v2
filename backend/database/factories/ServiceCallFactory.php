<?php

namespace Database\Factories;

use App\Enums\ServiceCallStatus;
use App\Models\Customer;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceCallFactory extends Factory
{
    protected $model = ServiceCall::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'created_by' => User::factory(),
            'call_number' => 'CT-'.str_pad($this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
            'scheduled_date' => $this->faker->optional(0.7)->dateTimeBetween('now', '+30 days'),
            'address' => $this->faker->optional(0.8)->streetAddress(),
            'city' => $this->faker->optional(0.8)->city(),
            'state' => $this->faker->optional(0.8)->randomElement(['SP', 'RJ', 'MG', 'PR', 'SC', 'RS', 'BA', 'ES', 'GO', 'DF']),
            'latitude' => $this->faker->optional(0.6)->latitude(-23.7, -22.5),
            'longitude' => $this->faker->optional(0.6)->longitude(-47.0, -43.0),
            'observations' => $this->faker->optional(0.5)->sentence(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => ServiceCallStatus::SCHEDULED->value,
            'technician_id' => User::factory(),
            'scheduled_date' => $this->faker->dateTimeBetween('now', '+7 days'),
        ]);
    }

    public function rescheduled(): static
    {
        return $this->state(fn () => [
            'status' => ServiceCallStatus::RESCHEDULED->value,
            'technician_id' => User::factory(),
            'reschedule_count' => 1,
        ]);
    }

    public function awaitingConfirmation(): static
    {
        return $this->state(fn () => [
            'status' => ServiceCallStatus::AWAITING_CONFIRMATION->value,
            'technician_id' => User::factory(),
        ]);
    }

    public function convertedToOs(): static
    {
        return $this->state(fn () => [
            'status' => ServiceCallStatus::CONVERTED_TO_OS->value,
            'technician_id' => User::factory(),
            'completed_at' => now(),
            'resolution_notes' => $this->faker->sentence(),
        ]);
    }
}
