<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EquipmentSchemaRegressionTest extends TestCase
{
    private function projectPath(string $filename): string
    {
        return dirname(__DIR__, 3).DIRECTORY_SEPARATOR.$filename;
    }

    private function createTableSql(string $contents, string $table): string
    {
        $pattern = '/CREATE TABLE "'.preg_quote($table, '/').'"\s*\(.+?\);/s';

        preg_match($pattern, $contents, $matches);

        $this->assertNotEmpty($matches, "CREATE TABLE statement for {$table} must exist in schema dump");

        return $matches[0];
    }

    public function test_sqlite_schema_dump_uses_english_default_for_equipment_status(): void
    {
        $contents = file_get_contents($this->projectPath('backend'.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'schema'.DIRECTORY_SEPARATOR.'sqlite-schema.sql'));

        $this->assertIsString($contents);
        $equipmentTableSql = $this->createTableSql($contents, 'equipments');

        // Accept both native SQLite format default ('active') and MySQL-compatible DEFAULT 'active'
        $hasNativeSqlite = str_contains($equipmentTableSql, '"status" varchar not null default (\'active\')');
        $hasMysqlCompat = str_contains($equipmentTableSql, '"status" varchar(30) NOT NULL DEFAULT \'active\'');
        $this->assertTrue($hasNativeSqlite || $hasMysqlCompat, 'Equipment status default must be "active" (English) in schema dump');
        // Ensure no Portuguese default for equipment status in CREATE TABLE
        $this->assertDoesNotMatchRegularExpression('/DEFAULT\s+\(?[\'"]ativo[\'"]\)?/i', $equipmentTableSql);
    }

    public function test_equipment_status_default_reconciliation_migration_is_guarded(): void
    {
        $contents = file_get_contents($this->projectPath('backend'.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'2026_03_20_130000_reconcile_equipments_status_default.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString("Schema::hasTable('equipments')", $contents);
        $this->assertStringContainsString("Schema::hasColumn('equipments', 'status')", $contents);
        $this->assertStringContainsString("DB::table('equipments')->where('status', 'ativo')->update(['status' => 'active'])", $contents);
        $this->assertStringContainsString('ALTER TABLE `equipments` MODIFY `status` VARCHAR(30) NOT NULL DEFAULT \'active\'', $contents);
        $this->assertStringContainsString('Forward-only corrective migration', $contents);
        $this->assertStringNotContainsString("update(['status' => 'ativo'])", $contents);
        $this->assertStringNotContainsString('->after(', $contents);
    }
}
