<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnalyticsDataset;
use App\Models\DataExportJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataExportJob>
 */
class DataExportJobFactory extends Factory
{
    protected $model = DataExportJob::class;

    public function definition(): array
    {
        $dataset = AnalyticsDataset::factory()->create();

        return [
            'tenant_id' => $dataset->tenant_id,
            'analytics_dataset_id' => $dataset->id,
            'created_by' => $dataset->created_by,
            'name' => $this->faker->words(2, true),
            'status' => DataExportJob::STATUS_PENDING,
            'source_modules' => $dataset->source_modules,
            'filters' => [],
            'output_format' => 'json',
            'output_path' => null,
            'file_size_bytes' => null,
            'rows_exported' => null,
            'started_at' => null,
            'completed_at' => null,
            'error_message' => null,
            'scheduled_cron' => null,
            'last_scheduled_at' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => DataExportJob::STATUS_FAILED,
            'error_message' => 'Falha simulada',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => DataExportJob::STATUS_COMPLETED,
            'completed_at' => now(),
            'rows_exported' => 10,
            'file_size_bytes' => 128,
        ]);
    }
}
