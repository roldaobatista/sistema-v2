<?php

/**
 * Generate SQLite schema dump by converting MySQL schema.
 *
 * QUANDO USAR: Sempre que criar ou alterar migrations.
 * REQUISITOS: MySQL Docker rodando (docker start sistema_mysql)
 *
 * Uso: php generate_sqlite_schema.php
 */
$start = microtime(true);

$host = env_or('DB_HOST', '127.0.0.1');
$port = env_or('DB_PORT', '3307');
$db = env_or('DB_DATABASE', 'kalibrium_testing');
$user = env_or('DB_USERNAME', 'sistema');
$pass = env_or('DB_PASSWORD', 'sistema');

try {
    $mysql = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
} catch (PDOException $e) {
    echo "ERRO: Não conseguiu conectar ao MySQL ($host:$port/$db)\n";
    echo "Verifique: docker start sistema_mysql\n";
    echo $e->getMessage()."\n";
    exit(1);
}
$mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$output = "-- SQLite Schema Dump (converted from MySQL)\n";
$output .= '-- Generated: '.date('Y-m-d H:i:s')."\n\n";

$tables = $mysql->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo 'Converting '.count($tables)." tables from MySQL to SQLite...\n";

foreach ($tables as $table) {
    $row = $mysql->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    $sql = $row['Create Table'];
    $sql = convertMysqlToSqlite($sql);
    $output .= "$sql;\n\n";
}

// Add migration records
$output .= "-- Migration records\n";
$migrations = $mysql->query('SELECT * FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($migrations as $m) {
    $migration = str_replace("'", "''", $m['migration']);
    $batch = (int) $m['batch'];
    $output .= "INSERT INTO \"migrations\" (\"id\", \"migration\", \"batch\") VALUES ({$m['id']}, '$migration', $batch);\n";
}

$schemaPath = __DIR__.'/database/schema/sqlite-schema.sql';
$schemaDir = dirname($schemaPath);
if (! is_dir($schemaDir)) {
    mkdir($schemaDir, 0775, true);
}
file_put_contents($schemaPath, $output);

// Verify: try loading into in-memory SQLite
echo "Verifying schema loads in SQLite...\n";
$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $sqlite->exec($output);
    $sqliteTables = $sqlite->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
    echo "Verification: $sqliteTables tables loaded OK\n";
} catch (Exception $e) {
    echo 'Verification FAILED: '.$e->getMessage()."\n";
    exit(1);
}

$elapsed = round(microtime(true) - $start, 1);
$size = round(strlen($output) / 1024);
echo "Done: {$size}KB, ".count($tables)." tables, {$elapsed}s\n";
echo "Saved to: $schemaPath\n";

// ──────────────────────────────────────────────────────────────
// Helper functions
// ──────────────────────────────────────────────────────────────

function env_or(string $key, string $default): string
{
    return getenv($key) ?: $default;
}

function convertMysqlToSqlite(string $sql): string
{
    // Remove MySQL-specific table options
    $sql = preg_replace('/\)\s*ENGINE\s*=\s*\w+[^;]*/i', ')', $sql);

    // Remove COLLATE on columns
    $sql = preg_replace('/\s+COLLATE\s+\w+/i', '', $sql);

    // Remove CHARACTER SET on columns
    $sql = preg_replace('/\s+CHARACTER\s+SET\s+\w+/i', '', $sql);

    // Remove inline "charset X" (MySQL-specific, appears inside CAST() of generated columns)
    $sql = preg_replace('/\s+charset\s+\w+/i', '', $sql);

    // Convert MySQL charset-prefixed string literals (_utf8mb4'...' -> '...').
    // Escopo restrito aos charsets que o MySQL emite para nao comer
    // sublinhados legitimos dentro de strings (ex: 'America/Sao_Paulo').
    $sql = preg_replace("/_(utf8|utf8mb3|utf8mb4|utf8mb5|latin1|ascii|binary|cp1252|big5|ujis|sjis)'/i", "'", $sql);

    // Remove COMMENT '...'
    $sql = preg_replace("/\s+COMMENT\s+'[^']*'/i", '', $sql);

    // Remove DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    $sql = preg_replace('/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $sql);

    // Convert AUTO_INCREMENT
    $sql = preg_replace('/\bbigint\b\s+unsigned\s+NOT\s+NULL\s+AUTO_INCREMENT/i', 'integer PRIMARY KEY AUTOINCREMENT NOT NULL', $sql);
    $sql = preg_replace('/\bbigint\b\s+NOT\s+NULL\s+AUTO_INCREMENT/i', 'integer PRIMARY KEY AUTOINCREMENT NOT NULL', $sql);
    $sql = preg_replace('/\bint\b\s+unsigned\s+NOT\s+NULL\s+AUTO_INCREMENT/i', 'integer PRIMARY KEY AUTOINCREMENT NOT NULL', $sql);
    $sql = preg_replace('/\bint\b\s+NOT\s+NULL\s+AUTO_INCREMENT/i', 'integer PRIMARY KEY AUTOINCREMENT NOT NULL', $sql);

    // Remove unsigned
    $sql = preg_replace('/\bunsigned\b/i', '', $sql);

    // Convert types
    $sql = preg_replace('/\bbigint\b/i', 'integer', $sql);
    $sql = preg_replace('/\bmediumint\b/i', 'integer', $sql);
    $sql = preg_replace('/\bsmallint\b/i', 'integer', $sql);
    $sql = preg_replace('/\btinyint\(\d+\)/i', 'tinyint', $sql);
    $sql = preg_replace('/\bint\(\d+\)/i', 'integer', $sql);
    $sql = preg_replace('/\bdouble\b/i', 'real', $sql);
    $sql = preg_replace('/\bfloat\b/i', 'real', $sql);
    $sql = preg_replace('/\bdecimal\(\d+,\s*\d+\)/i', 'numeric', $sql);
    $sql = preg_replace('/\bmediumtext\b/i', 'text', $sql);
    $sql = preg_replace('/\blongtext\b/i', 'text', $sql);
    $sql = preg_replace('/\bmediumblob\b/i', 'blob', $sql);
    $sql = preg_replace('/\blongblob\b/i', 'blob', $sql);
    $sql = preg_replace('/\bdatetime\b/i', 'datetime', $sql);
    $sql = preg_replace('/\btimestamp\b/i', 'datetime', $sql);
    $sql = preg_replace('/\bjson\b/i', 'text', $sql);

    // Convert enum to varchar
    $sql = preg_replace("/enum\([^)]+\)/i", 'varchar', $sql);

    // Extract UNIQUE KEYs before removing — create separate CREATE UNIQUE INDEX
    $uniqueIndexes = [];
    if (preg_match('/CREATE\s+TABLE\s+`([^`]+)`/i', $sql, $tableMatch)) {
        $tableName = $tableMatch[1];
        if (preg_match_all('/,\s*UNIQUE\s+KEY\s+`([^`]+)`\s*\(([^)]+)\)/i', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $idxName = $m[1];
                $cols = str_replace('`', '"', $m[2]);
                $uniqueIndexes[] = "CREATE UNIQUE INDEX \"{$idxName}\" ON \"{$tableName}\" ({$cols})";
            }
        }
    }

    // Remove KEY/INDEX definitions
    $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/,\s*KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/,\s*FULLTEXT\s+KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/,\s*SPATIAL\s+KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);

    // Append unique indexes after CREATE TABLE
    if (! empty($uniqueIndexes)) {
        $sql .= ";\n".implode(";\n", $uniqueIndexes);
    }

    // Remove CONSTRAINT ... FOREIGN KEY lines
    $sql = preg_replace('/,\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN\s+KEY\s*\([^)]+\)\s+REFERENCES\s+`[^`]+`\s*\([^)]+\)[^,\n)]*/i', '', $sql);

    // Remove any remaining ON DELETE/UPDATE CASCADE/SET NULL
    $sql = preg_replace('/\s+ON\s+(DELETE|UPDATE)\s+(CASCADE|SET\s+NULL|RESTRICT|NO\s+ACTION|SET\s+DEFAULT)/i', '', $sql);

    // Remove REFERENCES inline
    $sql = preg_replace('/\s+REFERENCES\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);

    // Remove PRIMARY KEY if already handled by AUTOINCREMENT
    if (stripos($sql, 'AUTOINCREMENT') !== false) {
        $sql = preg_replace('/,\s*PRIMARY\s+KEY\s*\(`[^`]+`\)/i', '', $sql);
    }

    // Convert backticks to double quotes
    $sql = str_replace('`', '"', $sql);

    // Clean up multiple NULLs
    $sql = preg_replace('/(NULL\s*){2,}/', 'NULL', $sql);

    // Clean up double spaces
    $sql = preg_replace('/  +/', ' ', $sql);

    // Clean up trailing commas before closing paren
    $sql = preg_replace('/,\s*\)/', "\n)", $sql);

    return $sql;
}
