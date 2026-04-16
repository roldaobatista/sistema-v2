<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\DatabaseBackup;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupDir = storage_path('app/backups');
        if (! is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test backup files
        foreach (glob("{$this->backupDir}/test_*.sql.gz") as $file) {
            @unlink($file);
        }
        foreach (glob("{$this->backupDir}/test_*.rdb.gz") as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_backup_command_has_default_retention_of_30_days(): void
    {
        $this->artisan('db:backup', ['--help' => true])
            ->assertSuccessful();

        // Verify the command signature defines retention default as 30
        $command = $this->app->make(Kernel::class)
            ->all()['db:backup'];

        $definition = $command->getDefinition();
        $retentionOption = $definition->getOption('retention');

        $this->assertEquals('30', $retentionOption->getDefault());
    }

    public function test_prune_removes_backups_older_than_retention(): void
    {
        // Create fake backup files with old timestamps
        $oldFile = "{$this->backupDir}/test_old_2026-01-01_000000.sql.gz";
        $recentFile = "{$this->backupDir}/test_recent_2026-04-03_000000.sql.gz";

        file_put_contents($oldFile, 'old backup');
        file_put_contents($recentFile, 'recent backup');

        // Set old file modification time to 40 days ago
        touch($oldFile, now()->subDays(40)->timestamp);
        // Set recent file modification time to 1 day ago
        touch($recentFile, now()->subDay()->timestamp);

        // Run backup with low retention for testing prune logic
        // The command will fail mysqldump but we test prune separately
        // Instead, use reflection to test prune method directly
        $command = new DatabaseBackup;
        $method = new \ReflectionMethod($command, 'pruneOldBackups');
        $method->setAccessible(true);
        $method->invoke($command, $this->backupDir, 30);

        $this->assertFileDoesNotExist($oldFile, 'Old backup should be pruned');
        $this->assertFileExists($recentFile, 'Recent backup should be kept');
    }

    public function test_prune_keeps_backups_within_retention_period(): void
    {
        $file1 = "{$this->backupDir}/test_keep1_2026-04-01_000000.sql.gz";
        $file2 = "{$this->backupDir}/test_keep2_2026-04-02_000000.sql.gz";

        file_put_contents($file1, 'backup 1');
        file_put_contents($file2, 'backup 2');

        touch($file1, now()->subDays(2)->timestamp);
        touch($file2, now()->subDay()->timestamp);

        $command = new DatabaseBackup;
        $method = new \ReflectionMethod($command, 'pruneOldBackups');
        $method->setAccessible(true);
        $method->invoke($command, $this->backupDir, 30);

        $this->assertFileExists($file1, 'Backup within retention should be kept');
        $this->assertFileExists($file2, 'Backup within retention should be kept');
    }
}
