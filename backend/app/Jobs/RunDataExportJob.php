<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DataExportJob;
use App\Services\Analytics\DatasetQueryBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RunDataExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public readonly int $dataExportJobId,
    ) {}

    public function handle(DatasetQueryBuilder $queryBuilder): void
    {
        $job = DataExportJob::query()->with('dataset')->find($this->dataExportJobId);

        if (! $job || ! $job->dataset || $job->status === DataExportJob::STATUS_CANCELLED) {
            return;
        }

        $job->forceFill([
            'status' => DataExportJob::STATUS_RUNNING,
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            $filters = [];
            foreach (($job->filters ?? []) as $key => $value) {
                if (is_string($key)) {
                    $filters[$key] = $value;
                }
            }

            $rows = $queryBuilder->execute($job->dataset, (int) $job->tenant_id, $filters);
            $content = $this->formatContent($rows, (string) $job->output_format);
            $filename = Str::slug($job->name).'-'.$job->id.'.'.$job->output_format;
            $path = "analytics/tenant-{$job->tenant_id}/{$filename}";

            Storage::disk('local')->put($path, $content);

            $job->forceFill([
                'status' => DataExportJob::STATUS_COMPLETED,
                'output_path' => $path,
                'rows_exported' => count($rows),
                'file_size_bytes' => strlen($content),
                'completed_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $job->forceFill([
                'status' => DataExportJob::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function formatContent(array $rows, string $format): string
    {
        if ($format === 'json') {
            return (string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $headers = array_keys($rows[0] ?? []);
        $lines = [];

        if ($headers !== []) {
            $lines[] = implode(',', $headers);
        }

        foreach ($rows as $row) {
            $values = $headers === [] ? array_values($row) : array_map(fn ($header) => $row[$header] ?? '', $headers);
            $lines[] = implode(',', array_map(
                fn ($value) => '"'.str_replace('"', '""', (string) $value).'"',
                $values
            ));
        }

        return implode(PHP_EOL, $lines);
    }
}
