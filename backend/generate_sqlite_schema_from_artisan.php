<?php

/**
 * Generate SQLite schema dump by running Laravel migrations against a temporary
 * SQLite file. Fallback do `generate_sqlite_schema.php` para ambientes onde
 * MySQL Docker não está disponível (Windows sem Docker Desktop, CI sem MySQL etc.).
 *
 * QUANDO USAR: alternativa ao generate_sqlite_schema.php quando MySQL/Docker
 * não está acessível. O dump gerado é equivalente — usa SQLite nativo direto
 * em vez de converter MySQL→SQLite.
 *
 * Uso: php generate_sqlite_schema_from_artisan.php
 */
$start = microtime(true);

$baseDir = __DIR__;
$tmpDb = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kalibrium_schema_dump_'.getmypid().'.sqlite';
$schemaPath = $baseDir.'/database/schema/sqlite-schema.sql';

@unlink($tmpDb);
touch($tmpDb);

echo "Temporary SQLite: $tmpDb\n";
echo "Running migrations (may take a while — 400+ migrations)...\n";

// Roda migrate:fresh contra o banco temporário com env de testing.
$env = [
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $tmpDb,
    'APP_ENV' => 'testing',
];
$envPrefix = '';
foreach ($env as $k => $v) {
    putenv("$k=$v");
    $envPrefix .= escapeshellarg("$k=$v").' ';
}

$cmd = 'php '.escapeshellarg($baseDir.'/artisan').' migrate:fresh --force --no-interaction 2>&1';
exec($cmd, $migrateOut, $migrateRc);
if ($migrateRc !== 0) {
    echo "ERRO: migrate:fresh falhou (rc=$migrateRc)\n";
    echo implode("\n", array_slice($migrateOut, -20))."\n";
    @unlink($tmpDb);
    exit(1);
}
echo 'Migrations OK ('.count($migrateOut)." linhas de output)\n";

// Conecta no SQLite gerado e extrai schema na ordem original.
$pdo = new PDO('sqlite:'.$tmpDb);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Lista tables (excluindo internas) e indexes na ordem em que foram criados
// (rowid de sqlite_master preserva ordem de criação).
$rows = $pdo->query(
    "SELECT type, name, tbl_name, sql FROM sqlite_master
     WHERE sql IS NOT NULL
       AND name NOT LIKE 'sqlite_%'
       AND name NOT LIKE 'sqlite_sequence'
     ORDER BY rowid"
)->fetchAll(PDO::FETCH_ASSOC);

$output = "-- SQLite Schema Dump (generated via artisan migrate)\n";
$output .= '-- Generated: '.date('Y-m-d H:i:s')."\n\n";

$tableCount = 0;
$indexCount = 0;
foreach ($rows as $row) {
    $sql = trim($row['sql']);
    if ($sql === '') {
        continue;
    }
    $output .= $sql.";\n\n";
    if ($row['type'] === 'table') {
        $tableCount++;
    } elseif ($row['type'] === 'index') {
        $indexCount++;
    }
}

// Migration records (necessário para schema:dump-style replay).
$output .= "-- Migration records\n";
$migs = $pdo->query('SELECT id, migration, batch FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($migs as $m) {
    $migration = str_replace("'", "''", $m['migration']);
    $batch = (int) $m['batch'];
    $output .= "INSERT INTO \"migrations\" (\"id\", \"migration\", \"batch\") VALUES ({$m['id']}, '$migration', $batch);\n";
}

if (! is_dir(dirname($schemaPath))) {
    mkdir(dirname($schemaPath), 0775, true);
}
file_put_contents($schemaPath, $output);

// Verifica que o dump recarrega sem erro em SQLite in-memory.
echo "Verifying schema reloads in-memory...\n";
$verify = new PDO('sqlite::memory:');
$verify->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $verify->exec($output);
    $count = $verify->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
    echo "Verification: $count tables loaded OK\n";
} catch (Throwable $e) {
    echo 'Verification FAILED: '.$e->getMessage()."\n";
    @unlink($tmpDb);
    exit(1);
}

@unlink($tmpDb);

$elapsed = round(microtime(true) - $start, 1);
$size = round(strlen($output) / 1024);
echo "Done: {$size}KB, {$tableCount} tables, {$indexCount} indexes, ".count($migs)." migration records, {$elapsed}s\n";
echo "Saved to: $schemaPath\n";
