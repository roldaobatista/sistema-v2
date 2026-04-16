<?php

namespace Database\Factories;

use App\Enums\TimeClassificationType;
use App\Models\JourneyBlock;
use App\Models\JourneyEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JourneyBlock>
 */
class JourneyBlockFactory extends Factory
{
    protected $model = JourneyBlock::class;

    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-7 days', 'now');
        $endedAt = (clone $startedAt)->modify('+'.$this->faker->numberBetween(15, 240).' minutes');
        $durationMinutes = (int) (($endedAt->getTimestamp() - $startedAt->getTimestamp()) / 60);

        return [
            'tenant_id' => Tenant::factory(),
            'journey_day_id' => null,
            'journey_entry_id' => JourneyEntry::factory(),
            'user_id' => User::factory(),
            'classification' => $this->faker->randomElement(TimeClassificationType::cases())->value,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => $durationMinutes,
            'source' => $this->faker->randomElement(['clock', 'os_checkin', 'displacement', 'manual']),
            'is_auto_classified' => true,
            'is_manually_adjusted' => false,
        ];
    }

    public function classification(TimeClassificationType $type): static
    {
        return $this->state(fn () => [
            'classification' => $type->value,
        ]);
    }

    public function manuallyAdjusted(User $adjustedBy, string $reason): static
    {
        return $this->state(fn () => [
            'is_auto_classified' => false,
            'is_manually_adjusted' => true,
            'adjusted_by' => $adjustedBy->id,
            'adjustment_reason' => $reason,
        ]);
    }

    public function forWorkOrder(int $workOrderId): static
    {
        return $this->state(fn () => [
            'work_order_id' => $workOrderId,
            'source' => 'os_checkin',
            'classification' => TimeClassificationType::EXECUCAO_SERVICO->value,
        ]);
    }

    public function forTimeClock(int $timeClockEntryId): static
    {
        return $this->state(fn () => [
            'time_clock_entry_id' => $timeClockEntryId,
            'source' => 'clock',
        ]);
    }
}
