<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Trait that creates a pre-built SQLite database file once,
 * then copies it for each test process instead of re-running migrations.
 *
 * This is MUCH faster than LazilyRefreshDatabase for large schemas (376+ migrations)
 * because file copy is instant vs parsing SQL/running migrations.
 *
 * Usage: Replace `use LazilyRefreshDatabase` with `use CreatesTestDatabase` in TestCase.
 */
trait CreatesTestDatabase
{
    protected static string $templateDb = '';

    protected static bool $templateReady = false;

    protected function refreshTestDatabase(): void
    {
        $this->ensureTemplateExists();
        $this->copyTemplateToMemory();
    }

    protected function ensureTemplateExists(): void
    {
        if (static::$templateReady) {
            return;
        }

        static::$templateDb = sys_get_temp_dir().'/kalibrium_test_template_'.getmypid().'.sqlite';

        // Build template database from schema dump + pending migrations
        if (file_exists(static::$templateDb)) {
            unlink(static::$templateDb);
        }

        // Create a temporary SQLite file and run migrations on it
        config(['database.connections.sqlite_template' => [
            'driver' => 'sqlite',
            'database' => static::$templateDb,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        // Load schema dump if it exists, then run pending migrations
        $schemaPath = database_path('schema/sqlite-schema.sql');
        if (file_exists($schemaPath)) {
            $pdo = new \PDO('sqlite:'.static::$templateDb);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode = OFF');
            $pdo->exec('PRAGMA synchronous = OFF');
            $pdo->exec('PRAGMA cache_size = -20000');
            $pdo->exec('PRAGMA temp_store = MEMORY');

            $sql = file_get_contents($schemaPath);
            $pdo->exec($sql);
            $pdo = null;
        }

        // Run any pending migrations on the template
        Artisan::call('migrate', [
            '--database' => 'sqlite_template',
            '--no-interaction' => true,
            '--force' => true,
        ]);

        // Disconnect so the file is unlocked
        DB::connection('sqlite_template')->disconnect();

        static::$templateReady = true;
    }

    protected function copyTemplateToMemory(): void
    {
        $connection = DB::connection('sqlite');
        $pdo = $connection->getPdo();

        // Drop all existing tables in memory DB (fast reset)
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name != 'sqlite_sequence'")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");
        }

        // Attach template and copy all data
        $templatePath = str_replace('\\', '/', static::$templateDb);
        $pdo->exec("ATTACH DATABASE '{$templatePath}' AS template_db");

        // Copy schema and data from template
        $templateTables = $pdo->query("SELECT name, sql FROM template_db.sqlite_master WHERE type='table' AND name != 'sqlite_sequence' ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($templateTables as $table) {
            if (! empty($table['sql'])) {
                $pdo->exec($table['sql']);
                $pdo->exec("INSERT INTO main.\"{$table['name']}\" SELECT * FROM template_db.\"{$table['name']}\"");
            }
        }

        // Copy indexes
        $indexes = $pdo->query("SELECT sql FROM template_db.sqlite_master WHERE type='index' AND sql IS NOT NULL")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($indexes as $sql) {
            $safeSql = str_replace('CREATE INDEX', 'CREATE INDEX IF NOT EXISTS', $sql);
            $pdo->exec($safeSql);
        }

        $pdo->exec('DETACH DATABASE template_db');
    }

    /**
     * Clean up the template database file when the process ends.
     */
    public static function tearDownAfterClassCreatesTestDatabase(): void
    {
        if (static::$templateDb && file_exists(static::$templateDb)) {
            @unlink(static::$templateDb);
        }
    }

    public function beginDatabaseTransaction(): void
    {
        // Wrap each test in a transaction for fast rollback instead of re-copying
        $connection = DB::connection('sqlite');
        $connection->beginTransaction();

        $this->beforeApplicationDestroyed(function () use ($connection) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        });
    }

    public function refreshDatabase(): void
    {
        $this->refreshTestDatabase();
        $this->beginDatabaseTransaction();
    }
}
