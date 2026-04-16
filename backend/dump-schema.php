<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Gera schema dump para testes.
 * Cria banco SQLite no disco, roda migrations, extrai schema, salva dump.
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);

$tempDb = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kalibrium_dump_'.time().'.sqlite';
touch($tempDb);
echo "Temp DB created: {$tempDb}\n";

// Set env BEFORE bootstrapping Laravel
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv("DB_DATABASE={$tempDb}");
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = $tempDb;
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = $tempDb;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Force config after bootstrap
config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $tempDb,
]);
DB::purge('sqlite');
DB::reconnect('sqlite');

echo "Running migrations on SQLite file...\n";
$start = microtime(true);

try {
    Artisan::call('migrate', [
        '--database' => 'sqlite',
        '--force' => true,
        '--no-interaction' => true,
    ]);
    $output = Artisan::output();
    echo "Migration output (last 3 lines):\n";
    $lines = array_filter(explode("\n", trim($output)));
    echo implode("\n", array_slice($lines, -3))."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo 'at: '.$e->getFile().':'.$e->getLine()."\n";
}

$elapsed = round(microtime(true) - $start, 2);
echo "Migrations completed in {$elapsed}s\n";

// Verify tables exist
$pdo = new PDO("sqlite:{$tempDb}");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableCount = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchColumn();
echo "Tables in DB: {$tableCount}\n";

if ($tableCount < 10) {
    echo "ERROR: Too few tables ({$tableCount}), aborting!\n";

    // Debug: show migrate status
    echo "\nMigration table exists? ";

    try {
        $migCount = $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
        echo "YES ({$migCount} entries)\n";
    } catch (PDOException $e) {
        echo 'NO - '.$e->getMessage()."\n";
    }

    // Show what tables DO exist
    echo "\nExisting tables:\n";
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        echo "  - {$t}\n";
    }

    @unlink($tempDb);
    exit(1);
}

// Extract schema
$tables = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$indexes = $pdo->query("SELECT sql FROM sqlite_master WHERE type='index' AND sql IS NOT NULL ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$migrations = $pdo->query('SELECT id, migration, batch FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$dump = "-- Schema dump for SQLite testing\n";
$dump .= '-- Generated: '.date('Y-m-d H:i:s')."\n";
$dump .= '-- Tables: '.count($tables).', Indexes: '.count($indexes).', Migrations: '.count($migrations)."\n\n";

foreach ($tables as $sql) {
    if ($sql) {
        $dump .= "{$sql};\n\n";
    }
}

$dump .= "\n-- Indexes\n\n";
foreach ($indexes as $sql) {
    if ($sql) {
        $dump .= "{$sql};\n\n";
    }
}

$dump .= "\n-- Migrations record\n";
foreach ($migrations as $m) {
    $mig = str_replace("'", "''", $m['migration']);
    $dump .= "INSERT INTO migrations (id, migration, batch) VALUES ({$m['id']}, '{$mig}', {$m['batch']});\n";
}

$outputPath = __DIR__.'/database/schema/sqlite-schema.sql';
file_put_contents($outputPath, $dump);

$size = round(filesize($outputPath) / 1024, 1);
echo "\n=== DONE ===\n";
echo "Schema dump: {$outputPath}\n";
echo "Size: {$size} KB\n";
echo 'Tables: '.count($tables)."\n";
echo 'Migrations: '.count($migrations)."\n";

@unlink($tempDb);
echo "Temp DB cleaned.\n";
