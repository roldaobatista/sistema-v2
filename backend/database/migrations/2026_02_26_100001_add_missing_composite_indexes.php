<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona índices compostos faltantes em tabelas críticas para performance por tenant.
 * Idempotente: verifica existência do índice antes de criar.
 */
return new class extends Migration
{
    private function indexExists(string $table, string $name): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            $result = DB::selectOne(
                "SELECT 1 FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ? LIMIT 1",
                [$table, $name]
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

    public function up(): void
    {
        if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'tenant_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                if (! $this->indexExists('stock_movements', 'stk_mov_tenant_idx')) {
                    $table->index('tenant_id', 'stk_mov_tenant_idx');
                }
                if (! $this->indexExists('stock_movements', 'stk_mov_tenant_type_idx')) {
                    $table->index(['tenant_id', 'type'], 'stk_mov_tenant_type_idx');
                }
                if (! $this->indexExists('stock_movements', 'stk_mov_tenant_created_idx')) {
                    $table->index(['tenant_id', 'created_at'], 'stk_mov_tenant_created_idx');
                }
            });
        }

        if (Schema::hasTable('work_orders')) {
            if (! $this->indexExists('work_orders', 'wo_tenant_created_idx')) {
                Schema::table('work_orders', function (Blueprint $table) {
                    $table->index(['tenant_id', 'created_at'], 'wo_tenant_created_idx');
                });
            }
        }

        if (Schema::hasTable('quotes')) {
            if (! $this->indexExists('quotes', 'qt_tenant_created_idx')) {
                Schema::table('quotes', function (Blueprint $table) {
                    $table->index(['tenant_id', 'created_at'], 'qt_tenant_created_idx');
                });
            }
        }

        if (Schema::hasTable('equipment_calibrations')) {
            Schema::table('equipment_calibrations', function (Blueprint $table) {
                if (! $this->indexExists('equipment_calibrations', 'eq_cal_tenant_status_idx')) {
                    $table->index(['tenant_id', 'status'], 'eq_cal_tenant_status_idx');
                }
                if (! $this->indexExists('equipment_calibrations', 'eq_cal_tenant_equip_idx')) {
                    $table->index(['tenant_id', 'equipment_id'], 'eq_cal_tenant_equip_idx');
                }
            });
        }

        if (Schema::hasTable('crm_deals')) {
            if (! $this->indexExists('crm_deals', 'crm_deals_tenant_cust_idx')) {
                Schema::table('crm_deals', function (Blueprint $table) {
                    $table->index(['tenant_id', 'customer_id'], 'crm_deals_tenant_cust_idx');
                });
            }
        }

        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'tenant_id')) {
            if (! $this->indexExists('notifications', 'notif_tenant_user_read_idx')) {
                Schema::table('notifications', function (Blueprint $table) {
                    $table->index(['tenant_id', 'notifiable_id', 'read_at'], 'notif_tenant_user_read_idx');
                });
            }
        }
    }

    public function down(): void
    {
        $drops = [
            'stock_movements' => ['stk_mov_tenant_idx', 'stk_mov_tenant_type_idx', 'stk_mov_tenant_created_idx'],
            'work_orders' => ['wo_tenant_created_idx'],
            'quotes' => ['qt_tenant_created_idx'],
            'equipment_calibrations' => ['eq_cal_tenant_status_idx', 'eq_cal_tenant_equip_idx'],
            'crm_deals' => ['crm_deals_tenant_cust_idx'],
            'notifications' => ['notif_tenant_user_read_idx'],
        ];

        foreach ($drops as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name) {
                if ($this->indexExists($table, $name)) {
                    Schema::table($table, function (Blueprint $table) use ($name) {
                        $table->dropIndex($name);
                    });
                }
            }
        }
    }
};
