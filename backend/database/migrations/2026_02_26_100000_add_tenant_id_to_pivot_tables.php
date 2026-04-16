<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona tenant_id às tabelas pivot que não possuem isolamento por tenant.
 * Preenche tenant_id a partir da tabela pai. Coluna nullable para registros órfãos.
 */
return new class extends Migration
{
    private array $tables = [
        'work_order_technicians' => 'work_orders',
        'work_order_equipments' => 'work_orders',
        'calibration_standard_weight' => 'equipment_calibrations',
        'equipment_model_product' => 'equipment_models',
        'quote_quote_tag' => 'quotes',
    ];

    public function up(): void
    {
        foreach ($this->tables as $pivot => $parent) {
            if (Schema::hasTable($pivot) && ! Schema::hasColumn($pivot, 'tenant_id')) {
                Schema::table($pivot, function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->nullable();
                });

                $parentIdCol = match ($pivot) {
                    'work_order_technicians', 'work_order_equipments' => 'work_order_id',
                    'calibration_standard_weight' => 'equipment_calibration_id',
                    'equipment_model_product' => 'equipment_model_id',
                    'quote_quote_tag' => 'quote_id',
                };

                if (Schema::hasTable($parent) && Schema::hasColumn($parent, 'tenant_id')) {
                    $driver = Schema::getConnection()->getDriverName();
                    if ($driver === 'sqlite') {
                        DB::statement("
                            UPDATE {$pivot}
                            SET tenant_id = (SELECT tenant_id FROM {$parent} WHERE {$parent}.id = {$pivot}.{$parentIdCol})
                            WHERE tenant_id IS NULL
                        ");
                    } else {
                        DB::statement("
                            UPDATE {$pivot} p
                            INNER JOIN {$parent} par ON p.{$parentIdCol} = par.id
                            SET p.tenant_id = par.tenant_id
                            WHERE p.tenant_id IS NULL
                        ");
                    }
                }

                $idxName = strlen($pivot) > 20 ? substr($pivot, 0, 20).'_tenant_idx' : $pivot.'_tenant_idx';
                Schema::table($pivot, function (Blueprint $table) use ($idxName) {
                    $table->index('tenant_id', $idxName);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $pivot) {
            if (! Schema::hasTable($pivot) || ! Schema::hasColumn($pivot, 'tenant_id')) {
                continue;
            }
            $idxName = strlen($pivot) > 20 ? substr($pivot, 0, 20).'_tenant_idx' : $pivot.'_tenant_idx';
            Schema::table($pivot, function (Blueprint $table) use ($idxName) {
                $table->dropIndex($idxName);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
