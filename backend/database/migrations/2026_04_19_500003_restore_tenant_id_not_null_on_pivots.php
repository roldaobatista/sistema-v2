<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-auditoria Camada 1 2026-04-19 — data-04.
 *
 * Restaura tenant_id NOT NULL nas tabelas pivot/line-item onde a FK pai
 * permite backfill seguro. Tabelas cameras e inmetro_instruments ficam
 * de fora (tabelas-raiz sem FK de backfill segura — wave própria).
 *
 * Processo por tabela:
 *  1. Backfill tenant_id via JOIN na FK pai conhecida.
 *  2. Se sobrar NULL após backfill, aborta com exception (conservador —
 *     não deleta dados).
 *  3. ALTER para NOT NULL.
 *
 * Idempotente: detecta se a coluna já é NOT NULL e pula.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{parent: string, fk: string, parent_key: string}>
     */
    private array $map = [
        'work_order_technicians' => [
            'parent' => 'work_orders',
            'fk' => 'work_order_id',
            'parent_key' => 'id',
        ],
        'work_order_equipments' => [
            'parent' => 'work_orders',
            'fk' => 'work_order_id',
            'parent_key' => 'id',
        ],
        'equipment_model_product' => [
            'parent' => 'equipment_models',
            'fk' => 'equipment_model_id',
            'parent_key' => 'id',
        ],
        'email_email_tag' => [
            'parent' => 'emails',
            'fk' => 'email_id',
            'parent_key' => 'id',
        ],
        'quote_quote_tag' => [
            'parent' => 'quotes',
            'fk' => 'quote_id',
            'parent_key' => 'id',
        ],
        'service_call_equipments' => [
            'parent' => 'service_calls',
            'fk' => 'service_call_id',
            'parent_key' => 'id',
        ],
        'service_skills' => [
            'parent' => 'services',
            'fk' => 'service_id',
            'parent_key' => 'id',
        ],
        'calibration_standard_weight' => [
            'parent' => 'equipment_calibrations',
            'fk' => 'equipment_calibration_id',
            'parent_key' => 'id',
        ],
        'purchase_quotation_items' => [
            'parent' => 'purchase_quotations',
            'fk' => 'purchase_quotation_id',
            'parent_key' => 'id',
        ],
        'inventory_items' => [
            'parent' => 'inventories',
            'fk' => 'inventory_id',
            'parent_key' => 'id',
        ],
    ];

    public function up(): void
    {
        foreach ($this->map as $table => $fk) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            if (! $this->columnIsNullable($table, 'tenant_id')) {
                continue; // já NOT NULL — idempotente
            }

            if (! Schema::hasTable($fk['parent']) || ! Schema::hasColumn($fk['parent'], 'tenant_id')) {
                continue; // pai indisponível — skip seguro
            }

            $this->backfillTenantIdFromParent($table, $fk['parent'], $fk['fk'], $fk['parent_key']);

            $orphans = DB::table($table)->whereNull('tenant_id')->count();
            if ($orphans > 0) {
                throw new RuntimeException(sprintf(
                    "[data-04] Tabela %s ainda tem %d linhas com tenant_id NULL após backfill via %s. ".
                        "Limpeza manual necessária antes de rodar a migration — não deletamos dados automaticamente.",
                    $table,
                    $orphans,
                    $fk['parent'],
                ));
            }

            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->map) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $info = DB::select("PRAGMA table_info('{$table}')");
            foreach ($info as $col) {
                if ($col->name === $column) {
                    return (int) $col->notnull === 0;
                }
            }

            return true; // coluna não encontrada → assume nullable (migration só age em hasColumn=true)
        }

        // MySQL / MariaDB
        $schema = DB::connection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$schema, $table, $column]
        );

        return $row !== null && strtoupper((string) $row->IS_NULLABLE) === 'YES';
    }

    private function backfillTenantIdFromParent(string $child, string $parent, string $fk, string $parentKey): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(
                "UPDATE {$child} SET tenant_id = (SELECT p.tenant_id FROM {$parent} p WHERE p.{$parentKey} = {$child}.{$fk}) WHERE tenant_id IS NULL"
            );

            return;
        }

        // MySQL/MariaDB: UPDATE ... JOIN
        DB::statement(
            "UPDATE {$child} c INNER JOIN {$parent} p ON p.{$parentKey} = c.{$fk} SET c.tenant_id = p.tenant_id WHERE c.tenant_id IS NULL"
        );
    }
};
