<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductionMigrationRegressionTest extends TestCase
{
    private function projectPath(string $filename): string
    {
        return dirname(__DIR__, 3).DIRECTORY_SEPARATOR.$filename;
    }

    private function migrationPath(string $filename): string
    {
        return $this->projectPath('backend'.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.$filename);
    }

    public function test_telescope_migration_is_guarded_for_existing_tables(): void
    {
        $contents = file_get_contents($this->migrationPath('2026_03_14_140947_create_telescope_entries_table.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString("if (! \$schema->hasTable('telescope_entries'))", $contents);
        $this->assertStringContainsString("if (! \$schema->hasTable('telescope_entries_tags'))", $contents);
        $this->assertStringContainsString("if (! \$schema->hasTable('telescope_monitoring'))", $contents);
    }

    public function test_pending_production_migrations_do_not_use_after_clauses(): void
    {
        $files = [
            '2026_03_16_000001_add_missing_columns_to_multiple_tables.php',
            '2026_03_16_110000_fix_missing_columns_for_tests.php',
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($this->migrationPath($file));

            $this->assertIsString($contents);
            $this->assertStringNotContainsString('->after(', $contents, $file.' ainda depende de after().');
        }
    }

    public function test_deploy_script_uses_safe_origin_tracking_fetch(): void
    {
        $contents = file_get_contents($this->projectPath('deploy'.DIRECTORY_SEPARATOR.'deploy.sh'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('git fetch --prune origin', $contents);
        $this->assertStringContainsString('refs/remotes/origin/main', $contents);
        $this->assertStringNotContainsString('git fetch origin main', $contents);
    }

    public function test_fk_fix_migration_uses_metadata_lookup_before_drop(): void
    {
        $contents = file_get_contents($this->migrationPath('2026_03_16_200000_fix_critical_fk_on_delete_behavior.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('information_schema.KEY_COLUMN_USAGE', $contents);
        $this->assertStringContainsString('information_schema.COLUMNS', $contents);
        $this->assertStringContainsString('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $contents);
        $this->assertStringContainsString('ALTER TABLE `%s` MODIFY `%s` %s NULL', $contents);
    }

    public function test_missing_columns_for_tests_migration_uses_short_index_names(): void
    {
        $contents = file_get_contents($this->migrationPath('2026_03_17_070000_add_missing_columns_for_tests.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString("'ar_inst_tenant_ar_idx'", $contents);
        $this->assertStringContainsString("'ap_inst_tenant_ap_idx'", $contents);
        $this->assertStringContainsString("'ptc_tenant_ticket_idx'", $contents);
    }

    public function test_v3_performance_indexes_migration_skips_text_and_blob_columns(): void
    {
        $contents = file_get_contents($this->migrationPath('2026_03_18_000002_infra_audit_v3_performance_indexes.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('information_schema.COLUMNS', $contents);
        $this->assertStringContainsString("'text', 'tinytext', 'mediumtext', 'longtext'", $contents);
        $this->assertStringContainsString('canSafelyIndexColumn($table, $column)', $contents);
    }

    public function test_accounts_payable_supplier_fk_reconciliation_migration_is_safe_for_legacy_production(): void
    {
        $contents = file_get_contents($this->migrationPath('2026_03_20_120000_reconcile_accounts_payable_supplier_fk.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString("Schema::hasTable('accounts_payable')", $contents);
        $this->assertStringContainsString("Schema::hasTable('suppliers')", $contents);
        $this->assertStringContainsString("Schema::hasColumn('accounts_payable', 'supplier_id')", $contents);
        $this->assertStringContainsString('information_schema.KEY_COLUMN_USAGE', $contents);
        $this->assertStringContainsString('dropForeignKeyIfExists', $contents);
        $this->assertStringContainsString('Intencionalmente no-op.', $contents);
    }
}
