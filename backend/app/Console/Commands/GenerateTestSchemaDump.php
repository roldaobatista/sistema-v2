<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class GenerateTestSchemaDump extends Command
{
    protected $signature = 'test:schema-dump {--prune : Remove migration files after dump}';

    protected $description = 'Generate SQLite schema dump for faster testing (bypasses sqlite3 CLI requirement)';

    public function handle(): int
    {
        $this->info('Creating temporary SQLite database...');

        $tmpDb = storage_path('app/test_schema_dump.sqlite');
        File::delete($tmpDb);
        touch($tmpDb);

        config(['database.connections.schema_dump' => [
            'driver' => 'sqlite',
            'database' => $tmpDb,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        $this->info('Running migrations on temporary database...');

        Artisan::call('migrate', [
            '--database' => 'schema_dump',
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->info('Extracting schema...');

        $tables = DB::connection('schema_dump')
            ->select("SELECT sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

        $indexes = DB::connection('schema_dump')
            ->select("SELECT sql FROM sqlite_master WHERE type='index' AND sql IS NOT NULL ORDER BY name");

        $schemaDir = database_path('schema');
        File::ensureDirectoryExists($schemaDir);

        $sql = "-- Auto-generated schema dump for testing\n";
        $sql .= '-- Generated at: '.now()->toIso8601String()."\n";
        $sql .= '-- Replaces running '.count(File::files(database_path('migrations')))." migrations\n\n";

        foreach ($tables as $table) {
            if ($table->sql) {
                $sql .= $table->sql.";\n\n";
            }
        }

        foreach ($indexes as $index) {
            if ($index->sql) {
                $sql .= $index->sql.";\n\n";
            }
        }

        // Include migrations table data so Laravel knows all migrations are applied
        $migrations = DB::connection('schema_dump')
            ->table('migrations')
            ->orderBy('id')
            ->get();

        if ($migrations->isNotEmpty()) {
            $sql .= "-- Migration records (prevents re-running migrations)\n";
            foreach ($migrations as $m) {
                $migration = str_replace("'", "''", $m->migration);
                $sql .= "INSERT INTO \"migrations\" (\"id\", \"migration\", \"batch\") VALUES ({$m->id}, '{$migration}', {$m->batch});\n";
            }
            $sql .= "\n";
        }

        $this->info('Included '.$migrations->count().' migration records in dump.');

        $outputPath = $schemaDir.'/sqlite-schema.sql';
        File::put($outputPath, $sql);

        File::delete($tmpDb);

        $this->info('Schema dump saved to: database/schema/sqlite-schema.sql');
        $this->info('Size: '.number_format(strlen($sql)).' bytes');

        return self::SUCCESS;
    }
}
