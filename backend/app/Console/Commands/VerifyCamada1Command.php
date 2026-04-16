<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verifica se as migrations da Camada 1 (multi-tenancy) foram aplicadas corretamente.
 * Pode ser executado em produção após migrate --force.
 */
class VerifyCamada1Command extends Command
{
    protected $signature = 'camada1:verify';

    protected $description = 'Verifica colunas tenant_id e índices das migrations de multi-tenancy (Camada 1)';

    public function handle(): int
    {
        $this->info('Verificando Camada 1 (pivot tables + inventory_items + índices)...');
        $ok = true;

        $pivotTables = [
            'work_order_technicians' => 'work_orders',
            'work_order_equipments' => 'work_orders',
            'calibration_standard_weight' => 'equipment_calibrations',
            'equipment_model_product' => 'equipment_models',
            'quote_quote_tag' => 'quotes',
            'email_email_tag' => 'emails',
            'service_call_equipments' => 'service_calls',
            'service_skills' => 'services',
            'agenda_item_watchers' => 'central_items',
            'agenda_item_dependencies' => 'central_items',
        ];

        foreach ($pivotTables as $table => $parent) {
            if (! Schema::hasTable($table)) {
                $this->warn("  [SKIP] Tabela {$table} não existe.");
                continue;
            }
            if (! Schema::hasColumn($table, 'tenant_id')) {
                $this->error("  [FALTA] {$table} não tem coluna tenant_id.");
                $ok = false;
            } else {
                $this->line("  [OK] {$table}.tenant_id");
            }
        }

        if (Schema::hasTable('inventory_items') && ! Schema::hasColumn('inventory_items', 'tenant_id')) {
            $this->error('  [FALTA] inventory_items não tem coluna tenant_id.');
            $ok = false;
        } elseif (Schema::hasTable('inventory_items')) {
            $this->line('  [OK] inventory_items.tenant_id');
        }

        $compositeIndexes = [
            'stock_movements' => ['stk_mov_tenant_idx', 'stk_mov_tenant_type_idx', 'stk_mov_tenant_created_idx'],
            'work_orders' => ['wo_tenant_created_idx'],
            'quotes' => ['qt_tenant_created_idx'],
            'equipment_calibrations' => ['eq_cal_tenant_status_idx', 'eq_cal_tenant_equip_idx'],
            'crm_deals' => ['crm_deals_tenant_cust_idx'],
            'notifications' => ['notif_tenant_user_read_idx'],
        ];

        $driver = Schema::getConnection()->getDriverName();
        foreach ($compositeIndexes as $table => $indexNames) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexNames as $indexName) {
                if (! $this->indexExists($table, $indexName, $driver)) {
                    $this->error("  [FALTA] Índice {$table}.{$indexName}");
                    $ok = false;
                } else {
                    $this->line("  [OK] {$table}.{$indexName}");
                }
            }
        }

        if ($ok) {
            $this->info('Camada 1: verificação OK.');

            return self::SUCCESS;
        }

        $this->error('Camada 1: há itens faltando. Rode as migrations pendentes.');

        return self::FAILURE;
    }

    private function indexExists(string $table, string $name, string $driver): bool
    {
        if ($driver === 'sqlite') {
            $result = DB::selectOne(
                'SELECT 1 FROM sqlite_master WHERE type = ? AND tbl_name = ? AND name = ? LIMIT 1',
                ['index', $table, $name]
            );
        } else {
            $db = DB::getDatabaseName();
            $result = DB::selectOne(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$db, $table, $name]
            );
        }

        return $result !== null;
    }
}
