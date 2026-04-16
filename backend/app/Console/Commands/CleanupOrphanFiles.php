<?php

namespace App\Console\Commands;

use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanFiles extends Command
{
    protected $signature = 'cleanup:orphan-files {--dry-run : List files without deleting}';

    protected $description = 'Remove orphan files from work-orders Storage that are not referenced in the database';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $dryRun = $this->option('dry-run');
        $removed = 0;
        $skipped = 0;

        // 1. Check work-orders attachments directory
        $directories = $disk->directories('work-orders');

        foreach ($directories as $dir) {
            // Extract work_order_id from path like "work-orders/123"
            $woId = (int) basename($dir);
            if (! $woId) {
                continue;
            }

            $woExists = WorkOrder::withTrashed()->where('id', $woId)->exists();

            if (! $woExists) {
                $files = $disk->allFiles($dir);
                foreach ($files as $file) {
                    if ($dryRun) {
                        $this->line("[DRY-RUN] Would delete: {$file}");
                    } else {
                        $disk->delete($file);
                    }
                    $removed++;
                }

                if (! $dryRun && empty($disk->allFiles($dir))) {
                    $disk->deleteDirectory($dir);
                }
                continue;
            }

            // Check individual attachment files
            $attachmentFiles = $disk->files("{$dir}/attachments");
            foreach ($attachmentFiles as $file) {
                $referenced = WorkOrderAttachment::where('file_path', $file)->exists();
                if (! $referenced) {
                    if ($dryRun) {
                        $this->line("[DRY-RUN] Would delete orphan attachment: {$file}");
                    } else {
                        $disk->delete($file);
                    }
                    $removed++;
                } else {
                    $skipped++;
                }
            }
        }

        $action = $dryRun ? 'Found' : 'Removed';
        $this->info("{$action} {$removed} orphan file(s). {$skipped} valid file(s) kept.");
        Log::info("CleanupOrphanFiles: {$action} {$removed} orphan files, {$skipped} kept.", ['dry_run' => $dryRun]);

        return self::SUCCESS;
    }
}
