<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AuditPruneCommand extends Command
{
    protected $signature = 'audit:prune
                            {--months= : Override retention in months (default: config audit.prune_retention_months)}
                            {--dry-run : Only count records, do not export or delete}
                            {--disk= : Override storage disk (e.g. local, s3)}
                            {--format= : Export format: json or csv}
                            {--chunk= : Chunk size for processing}';

    protected $description = 'Export audit logs older than retention to compressed file in storage, then delete them (compliance + MySQL performance).';

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?? config('audit.prune_retention_months'));
        $dryRun = $this->option('dry-run');
        $disk = $this->option('disk') ?? config('audit.prune_disk');
        $format = $this->option('format') ?? config('audit.prune_export_format');
        $chunkSize = (int) ($this->option('chunk') ?? config('audit.prune_chunk_size'));

        if (! in_array($format, ['json', 'csv'], true)) {
            $this->error('Option --format must be json or csv.');

            return self::FAILURE;
        }

        $disks = array_keys(config('filesystems.disks', []));
        if (! in_array($disk, $disks, true)) {
            $this->error("Disk [{$disk}] is not configured. Available: ".implode(', ', $disks));

            return self::FAILURE;
        }

        if ($months < 1) {
            $this->error('Option --months must be >= 1.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subMonths($months);
        $this->info("Cutoff date: {$cutoff->toIso8601String()} (older than {$months} months).");

        $query = AuditLog::withoutGlobalScope('tenant')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id');

        $total = $query->count();
        if ($total === 0) {
            $this->info('No audit records to prune.');

            return self::SUCCESS;
        }

        $this->info("Found {$total} record(s) to prune.");

        if ($dryRun) {
            $this->warn('Dry run: no export or delete performed.');

            return self::SUCCESS;
        }

        $tmpDir = sys_get_temp_dir();
        $prefix = 'audit_prune_'.date('Ymd_His').'_';
        $tmpFile = $tmpDir.'/'.$prefix.'export.'.($format === 'csv' ? 'csv' : 'json');
        $tmpGz = $tmpFile.'.gz';

        $idsToDelete = [];
        $fp = fopen($tmpFile, 'w');
        if ($fp === false) {
            $this->error('Failed to create temp export file.');

            return self::FAILURE;
        }

        try {
            if ($format === 'csv') {
                $this->writeCsvHeader($fp);
            }

            $query->chunkById($chunkSize, function ($chunk) use ($fp, $format, &$idsToDelete) {
                foreach ($chunk as $row) {
                    $idsToDelete[] = $row->id;
                    $this->writeRow($fp, $row, $format);
                }
            });

            fclose($fp);
            $fp = null;

            $this->compressFile($tmpFile, $tmpGz);

            $basePath = config('audit.prune_storage_path');
            $datePath = $cutoff->format('Y/m');
            $filename = 'audit_logs_'.date('Ymd_His').'.'.$format.'.gz';
            $storagePath = rtrim($basePath, '/').'/'.$datePath.'/'.$filename;

            $this->info("Uploading to disk [{$disk}]: {$storagePath}");

            $stream = fopen($tmpGz, 'r');
            if ($stream === false) {
                throw new RuntimeException('Failed to open gzipped file for upload.');
            }

            $written = Storage::disk($disk)->put($storagePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $written) {
                $this->error('Failed to save file to storage. Aborting delete.');

                return self::FAILURE;
            }

            $this->info('File saved successfully. Deleting pruned records from database.');

            $deleteChunks = array_chunk($idsToDelete, $chunkSize);
            $deleted = 0;
            foreach ($deleteChunks as $ids) {
                $deleted += AuditLog::withoutGlobalScope('tenant')->whereIn('id', $ids)->delete();
            }

            $this->info("Prune complete: {$deleted} record(s) deleted, file at {$storagePath}.");
        } finally {
            if ($fp !== null && is_resource($fp)) {
                fclose($fp);
            }
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            if (file_exists($tmpGz)) {
                @unlink($tmpGz);
            }
        }

        return self::SUCCESS;
    }

    private function writeCsvHeader($fp): void
    {
        $cols = [
            'id', 'tenant_id', 'user_id', 'action', 'auditable_type', 'auditable_id',
            'description', 'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at',
        ];
        fputcsv($fp, $cols);
    }

    private function writeRow($fp, AuditLog $row, string $format): void
    {
        if ($format === 'csv') {
            $createdAt = $row->created_at?->format('Y-m-d H:i:s') ?? '';
            $action = $row->action instanceof \BackedEnum ? $row->action->value : (string) $row->action;
            fputcsv($fp, [
                $row->id,
                $row->tenant_id,
                $row->user_id,
                $action,
                $row->auditable_type,
                $row->auditable_id,
                $row->description,
                is_array($row->old_values) ? (json_encode($row->old_values, JSON_UNESCAPED_UNICODE) ?: '') : (string) $row->old_values,
                is_array($row->new_values) ? (json_encode($row->new_values, JSON_UNESCAPED_UNICODE) ?: '') : (string) $row->new_values,
                $row->ip_address,
                $row->user_agent,
                $createdAt,
            ]);
        } else {
            $payload = $row->only([
                'id', 'tenant_id', 'user_id', 'action', 'auditable_type', 'auditable_id',
                'description', 'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at',
            ]);
            if ($payload['action'] instanceof \BackedEnum) {
                $payload['action'] = $payload['action']->value;
            }
            if ($payload['created_at'] instanceof \DateTimeInterface) {
                $payload['created_at'] = $payload['created_at']->format('c');
            }
            fwrite($fp, json_encode($payload, JSON_UNESCAPED_UNICODE)."\n");
        }
    }

    private function compressFile(string $source, string $dest): void
    {
        $gz = gzopen($dest, 'w9');
        if ($gz === false) {
            throw new RuntimeException('Failed to create gzip file.');
        }
        $fp = fopen($source, 'r');
        if ($fp === false) {
            gzclose($gz);

            throw new RuntimeException('Failed to open source file for compression.');
        }
        while (! feof($fp)) {
            $chunk = fread($fp, 65536);
            if ($chunk !== false && $chunk !== '') {
                gzwrite($gz, $chunk);
            }
        }
        fclose($fp);
        gzclose($gz);
    }
}
