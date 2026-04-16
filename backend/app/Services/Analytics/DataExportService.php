<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Jobs\RunDataExportJob;
use App\Models\AnalyticsDataset;
use App\Models\DataExportJob;

class DataExportService
{
    /**
     * @param  array<int|string, mixed>  $payload
     */
    public function createJob(AnalyticsDataset $dataset, array $payload, int $tenantId, int $userId): DataExportJob
    {
        $job = DataExportJob::query()->create([
            'tenant_id' => $tenantId,
            'analytics_dataset_id' => $dataset->id,
            'created_by' => $userId,
            'name' => $payload['name'],
            'status' => DataExportJob::STATUS_PENDING,
            'source_modules' => $dataset->source_modules,
            'filters' => $payload['filters'] ?? [],
            'output_format' => $payload['output_format'],
            'scheduled_cron' => $payload['scheduled_cron'] ?? null,
        ]);

        RunDataExportJob::dispatch($job->id);

        return $job->fresh(['dataset']);
    }

    public function retry(DataExportJob $job): DataExportJob
    {
        $job->forceFill([
            'status' => DataExportJob::STATUS_PENDING,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ])->save();

        RunDataExportJob::dispatch($job->id);

        return $job->fresh(['dataset']);
    }

    public function cancel(DataExportJob $job): DataExportJob
    {
        $job->forceFill([
            'status' => DataExportJob::STATUS_CANCELLED,
        ])->save();

        return $job->fresh(['dataset']);
    }
}
