<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Notifications\SystemAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class CheckSystemAlerts extends Command
{
    protected $signature = 'system:check-alerts';

    protected $description = 'Check system health thresholds and send proactive alerts';

    public function handle(): int
    {
        if (! config('health.alerts.enabled')) {
            $this->info('System alerts disabled in config.');

            return self::SUCCESS;
        }

        $email = config('app.system_alert_email');
        if (! $email) {
            $this->warn('No system_alert_email configured. Skipping alerts.');

            return self::SUCCESS;
        }

        $alerts = [];

        $this->checkQueueThreshold($alerts);
        $this->checkDiskThreshold($alerts);
        $this->checkErrorCountThreshold($alerts);

        foreach ($alerts as $alert) {
            Notification::route('mail', $email)
                ->notify(new SystemAlertNotification(
                    $alert['title'],
                    $alert['body'],
                    'warning'
                ));

            Log::warning('System alert triggered', $alert);
            $this->warn("[ALERT] {$alert['title']}: {$alert['body']}");
        }

        if (empty($alerts)) {
            $this->info('All system health checks within thresholds.');
        } else {
            $this->warn(count($alerts).' alert(s) dispatched.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int|string, mixed>  $alerts
     */
    private function checkQueueThreshold(array &$alerts): void
    {
        $threshold = (int) config('health.alerts.queue_pending_threshold', 100);

        try {
            $pending = Queue::size('default');

            if ($pending > $threshold) {
                $alerts[] = [
                    'title' => 'Queue Overloaded',
                    'body' => "Queue has {$pending} pending jobs (threshold: {$threshold}).",
                    'metric' => 'queue_pending',
                    'value' => $pending,
                    'threshold' => $threshold,
                ];
            }
        } catch (\Throwable $e) {
            Log::error('CheckSystemAlerts: failed to check queue size', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $alerts
     */
    private function checkDiskThreshold(array &$alerts): void
    {
        $threshold = (int) config('health.alerts.disk_usage_threshold', 80);

        try {
            $usedPercent = $this->getDiskUsagePercent();

            if ($usedPercent > $threshold) {
                $alerts[] = [
                    'title' => 'Disk Usage Critical',
                    'body' => "Disk usage at {$usedPercent}% (threshold: {$threshold}%).",
                    'metric' => 'disk_usage',
                    'value' => $usedPercent,
                    'threshold' => $threshold,
                ];
            }
        } catch (\Throwable $e) {
            Log::error('CheckSystemAlerts: failed to check disk usage', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $alerts
     */
    private function checkErrorCountThreshold(array &$alerts): void
    {
        $threshold = (int) config('health.alerts.error_count_threshold', 5);

        try {
            $errorCount = $this->getRecentErrorCount();

            if ($errorCount > $threshold) {
                $alerts[] = [
                    'title' => 'Error Count Elevated',
                    'body' => "Detected {$errorCount} failed jobs in the last 15 minutes (threshold: {$threshold}).",
                    'metric' => 'error_count',
                    'value' => $errorCount,
                    'threshold' => $threshold,
                ];
            }
        } catch (\Throwable $e) {
            Log::error('CheckSystemAlerts: failed to check error count', ['error' => $e->getMessage()]);
        }
    }

    public function getDiskUsagePercent(): float
    {
        $storagePath = storage_path();
        $free = disk_free_space($storagePath);
        $total = disk_total_space($storagePath);

        if ($free === false || $total === false || $total <= 0) {
            return 100.0;
        }

        return round((1 - ($free / $total)) * 100, 2);
    }

    private function getRecentErrorCount(): int
    {
        // Count exceptions logged in the last 15 minutes from the failed_jobs table
        // and from Laravel's exception log channel if available
        $failedJobsCount = 0;

        try {
            if (Schema::hasTable('failed_jobs')) {
                $failedJobsCount = DB::table('failed_jobs')
                    ->where('failed_at', '>=', now()->subMinutes(15))
                    ->count();
            }
        } catch (\Throwable) {
            // Table may not exist in test environments
        }

        return $failedJobsCount;
    }
}
