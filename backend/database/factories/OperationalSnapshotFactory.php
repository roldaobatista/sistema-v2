<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OperationalSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalSnapshot>
 */
class OperationalSnapshotFactory extends Factory
{
    protected $model = OperationalSnapshot::class;

    public function definition(): array
    {
        return [
            'status' => $this->faker->randomElement(['healthy', 'degraded', 'critical']),
            'alerts_count' => $this->faker->numberBetween(0, 4),
            'health_payload' => [
                'status' => 'healthy',
                'checks' => [
                    'mysql' => ['ok' => true],
                    'redis' => ['ok' => true],
                ],
            ],
            'metrics_payload' => [
                ['path' => '/api/health', 'count' => 5, 'p95_ms' => 32.5],
            ],
            'alerts_payload' => [],
            'captured_at' => now(),
        ];
    }
}
