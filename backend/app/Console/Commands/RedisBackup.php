<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class RedisBackup extends Command
{
    protected $signature = 'redis:backup {--retention=30 : Days to keep old backups}';

    protected $description = 'Backup the Redis RDB dump file';

    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $connArgs = $this->buildConnectionArgs();

        // Capture LASTSAVE BEFORE triggering BGSAVE to avoid race condition
        $lastSaveResult = Process::run(array_merge(['redis-cli'], $connArgs, ['LASTSAVE']));
        $lastSaveBefore = (int) trim($lastSaveResult->output());

        // Trigger BGSAVE
        $result = Process::run(array_merge(['redis-cli'], $connArgs, ['BGSAVE']));
        if (! $result->successful()) {
            Log::error('Redis BGSAVE failed', ['output' => $result->errorOutput()]);
            $this->error('Redis BGSAVE failed: '.$result->errorOutput());

            return self::FAILURE;
        }

        // Wait for BGSAVE to complete (poll LASTSAVE)
        $maxWait = app()->runningUnitTests() ? 1 : 30;
        $waited = 0;
        while ($waited < $maxWait) {
            sleep(1);
            $waited++;
            $pollResult = Process::run(array_merge(['redis-cli'], $connArgs, ['LASTSAVE']));
            if (! $pollResult->successful()) {
                Log::warning('Redis LASTSAVE poll failed', ['output' => $pollResult->errorOutput()]);

                continue;
            }
            $lastSaveNow = (int) trim($pollResult->output());
            if ($lastSaveNow > $lastSaveBefore) {
                break;
            }
        }

        // Find the dump.rdb file
        $dirResult = Process::run(array_merge(['redis-cli'], $connArgs, ['CONFIG', 'GET', 'dir']));
        $filenameResult = Process::run(array_merge(['redis-cli'], $connArgs, ['CONFIG', 'GET', 'dbfilename']));

        $dirLines = array_filter(explode("\n", trim($dirResult->output())));
        $filenameLines = array_filter(explode("\n", trim($filenameResult->output())));

        $redisDir = end($dirLines) ?: '/data';
        $redisFilename = end($filenameLines) ?: 'dump.rdb';

        $sourcePath = rtrim($redisDir, '/').'/'.$redisFilename;

        if (! file_exists($sourcePath)) {
            Log::error('Redis dump file not found', ['path' => $sourcePath]);
            $this->error("Redis dump file not found at: {$sourcePath}");

            return self::FAILURE;
        }

        $destination = sprintf('%s/redis_%s.rdb.gz', $backupDir, now()->format('Y-m-d_His'));

        // Compress and copy using shell for redirection support
        $exitCode = 0;
        $output = [];
        exec(sprintf('gzip -c %s > %s 2>&1', escapeshellarg($sourcePath), escapeshellarg($destination)), $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($destination)) {
            Log::error('Redis backup compression failed', ['output' => implode("\n", $output)]);
            $this->error('Failed to compress Redis backup.');

            return self::FAILURE;
        }

        $sizeMb = round(filesize($destination) / 1024 / 1024, 2);
        Log::info('Redis backup completed', ['file' => $destination, 'size_mb' => $sizeMb]);
        $this->info("Redis backup saved: {$destination} ({$sizeMb} MB)");

        $retention = (int) $this->option('retention');
        $this->pruneOldBackups($backupDir, $retention);

        return self::SUCCESS;
    }

    /**
     * Build redis-cli connection arguments as an array (safe from shell injection).
     *
     * @return list<string>
     */
    private function buildConnectionArgs(): array
    {
        $args = [];
        $args[] = '-h';
        $args[] = (string) config('database.redis.default.host', '127.0.0.1');
        $args[] = '-p';
        $args[] = (string) config('database.redis.default.port', 6379);

        $password = config('database.redis.default.password', '');
        if ($password !== '' && $password !== null) {
            $args[] = '-a';
            $args[] = (string) $password;
            $args[] = '--no-auth-warning';
        }

        return $args;
    }

    private function pruneOldBackups(string $dir, int $retentionDays): void
    {
        $threshold = now()->subDays($retentionDays)->timestamp;

        $files = glob("{$dir}/redis_*.rdb.gz");
        if (! is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                try {
                    unlink($file);
                    $this->line('Pruned old Redis backup: '.basename($file));
                } catch (\Throwable $e) {
                    Log::warning('RedisBackup: failed to prune '.basename($file), ['error' => $e->getMessage()]);
                }
            }
        }
    }
}
