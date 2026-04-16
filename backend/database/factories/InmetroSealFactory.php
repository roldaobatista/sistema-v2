<?php

namespace Database\Factories;

use App\Models\InmetroSeal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InmetroSealFactory extends Factory
{
    protected $model = InmetroSeal::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => fake()->randomElement([InmetroSeal::TYPE_LACRE, InmetroSeal::TYPE_SELO_REPARO]),
            'number' => fake()->unique()->numerify('RS-######'),
            'status' => InmetroSeal::STATUS_AVAILABLE,
            'psei_status' => InmetroSeal::PSEI_NOT_APPLICABLE,
            'deadline_status' => InmetroSeal::DEADLINE_OK,
        ];
    }

    public function seloReparo(): static
    {
        return $this->state(['type' => InmetroSeal::TYPE_SELO_REPARO]);
    }

    public function lacre(): static
    {
        return $this->state(['type' => InmetroSeal::TYPE_LACRE]);
    }

    public function available(): static
    {
        return $this->state(['status' => InmetroSeal::STATUS_AVAILABLE]);
    }

    public function assigned(?int $technicianId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InmetroSeal::STATUS_ASSIGNED,
            'assigned_to' => $technicianId ?? User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'assigned_at' => now(),
        ]);
    }

    public function used(?int $workOrderId = null): static
    {
        return $this->state([
            'status' => InmetroSeal::STATUS_USED,
            'used_at' => now(),
            'photo_path' => 'seals/test-photo.jpg',
            'work_order_id' => $workOrderId,
        ]);
    }

    public function pendingPsei(): static
    {
        return $this->seloReparo()->state([
            'status' => InmetroSeal::STATUS_PENDING_PSEI,
            'psei_status' => InmetroSeal::PSEI_PENDING,
            'used_at' => now(),
            'deadline_at' => now()->addWeekdays(5),
            'photo_path' => 'seals/test-photo.jpg',
        ]);
    }

    public function registered(): static
    {
        return $this->seloReparo()->state([
            'status' => InmetroSeal::STATUS_REGISTERED,
            'psei_status' => InmetroSeal::PSEI_CONFIRMED,
            'psei_protocol' => fake()->numerify('PSEI-########'),
            'psei_submitted_at' => now(),
            'deadline_status' => InmetroSeal::DEADLINE_RESOLVED,
            'used_at' => now()->subDays(2),
            'photo_path' => 'seals/test-photo.jpg',
        ]);
    }

    public function overdue(): static
    {
        return $this->seloReparo()->state([
            'status' => InmetroSeal::STATUS_PENDING_PSEI,
            'psei_status' => InmetroSeal::PSEI_PENDING,
            'used_at' => now()->subDays(7),
            'deadline_at' => now()->subDays(2),
            'deadline_status' => InmetroSeal::DEADLINE_OVERDUE,
            'photo_path' => 'seals/test-photo.jpg',
        ]);
    }

    public function damaged(): static
    {
        return $this->state([
            'status' => InmetroSeal::STATUS_DAMAGED,
            'notes' => fake()->sentence(),
        ]);
    }

    public function lost(): static
    {
        return $this->state([
            'status' => InmetroSeal::STATUS_LOST,
            'notes' => fake()->sentence(),
        ]);
    }

    public function returned(): static
    {
        return $this->state([
            'status' => InmetroSeal::STATUS_RETURNED,
            'returned_at' => now(),
            'returned_reason' => fake()->sentence(),
        ]);
    }
}
