<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\RedisBackup;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RedisBackupTest extends TestCase
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
        foreach (glob("{$this->backupDir}/redis_*.rdb.gz") as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_redis_backup_command_exists(): void
    {
        $this->artisan('redis:backup', ['--help' => true])
            ->assertSuccessful();
    }

    public function test_redis_backup_has_default_retention_of_30_days(): void
    {
        $command = $this->app->make(Kernel::class)
            ->all()['redis:backup'];

        $definition = $command->getDefinition();
        $retentionOption = $definition->getOption('retention');

        $this->assertEquals('30', $retentionOption->getDefault());
    }

    public function test_redis_backup_prunes_old_files(): void
    {
        $oldFile = "{$this->backupDir}/redis_old_2026-01-01_000000.rdb.gz";
        $recentFile = "{$this->backupDir}/redis_recent_2026-04-03_000000.rdb.gz";

        file_put_contents($oldFile, 'old redis backup');
        file_put_contents($recentFile, 'recent redis backup');

        touch($oldFile, now()->subDays(40)->timestamp);
        touch($recentFile, now()->subDay()->timestamp);

        $command = new RedisBackup;
        $method = new \ReflectionMethod($command, 'pruneOldBackups');
        $method->setAccessible(true);
        $method->invoke($command, $this->backupDir, 30);

        $this->assertFileDoesNotExist($oldFile, 'Old Redis backup should be pruned');
        $this->assertFileExists($recentFile, 'Recent Redis backup should be kept');
    }

    public function test_redis_backup_handles_missing_dump_rdb_gracefully(): void
    {
        // Fake Process to simulate redis-cli returning a path that doesn't exist
        Process::fake([
            'redis-cli*BGSAVE*' => Process::result(output: 'Background saving started'),
            'redis-cli*LASTSAVE*' => Process::result(output: (string) time()),
            'redis-cli*CONFIG GET dir*' => Process::result(output: "dir\n/nonexistent/path"),
            'redis-cli*CONFIG GET dbfilename*' => Process::result(output: "dbfilename\ndump.rdb"),
        ]);

        $this->artisan('redis:backup')
            ->assertFailed();
    }
}
