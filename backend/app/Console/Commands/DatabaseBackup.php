<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup {--retention=30 : Days to keep old backups}';

    protected $description = 'Backup the MySQL database using mysqldump';

    public function handle(): int
    {
        $dbHost = config('database.connections.mysql.host');
        $dbPort = config('database.connections.mysql.port', 3306);
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');

        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = sprintf('%s/%s_%s.sql.gz', $backupDir, $dbName, now()->format('Y-m-d_His'));

        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --quick --lock-tables=false %s | gzip > %s',
            escapeshellarg($dbHost),
            escapeshellarg((string) $dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($filename)
        );

        $result = null;
        $output = [];
        putenv("MYSQL_PWD={$dbPass}");
        exec($command.' 2>&1', $output, $result);
        putenv('MYSQL_PWD');

        if ($result !== 0) {
            $error = implode("\n", $output);
            Log::error('Database backup failed', ['output' => $error]);
            $this->error("Backup failed: {$error}");

            return self::FAILURE;
        }

        $sizeMb = round(filesize($filename) / 1024 / 1024, 2);
        Log::info('Database backup completed', ['file' => $filename, 'size_mb' => $sizeMb]);
        $this->info("Backup saved: {$filename} ({$sizeMb} MB)");

        $retention = (int) $this->option('retention');
        $this->pruneOldBackups($backupDir, $retention);

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $dir, int $retentionDays): void
    {
        $threshold = now()->subDays($retentionDays)->timestamp;

        $files = glob("{$dir}/*.sql.gz");
        if (! is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                try {
                    unlink($file);
                    $this->line('Pruned old backup: '.basename($file));
                } catch (\Throwable $e) {
                    Log::warning('DatabaseBackup: failed to prune '.basename($file), ['error' => $e->getMessage()]);
                }
            }
        }
    }
}
