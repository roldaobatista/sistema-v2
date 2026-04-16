<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona tenant_id às pivot tables restantes para isolamento por tenant.
 * gamification_user_badges já possui tenant_id (criada com a coluna).
 * Preenche a partir da tabela pai. Coluna nullable para registros órfãos.
 */
return new class extends Migration
{
    private array $tables = [
        'service_skills' => ['parent' => 'services', 'parent_id_col' => 'service_id'],
        'central_item_watchers' => ['parent' => 'central_items', 'parent_id_col' => 'agenda_item_id'],
        'central_item_dependencies' => ['parent' => 'central_items', 'parent_id_col' => 'item_id'],
    ];

    public function up(): void
    {
        foreach ($this->tables as $pivot => $config) {
            $parent = $config['parent'];
            $parentIdCol = $config['parent_id_col'];

            if (! Schema::hasTable($pivot) || Schema::hasColumn($pivot, 'tenant_id')) {
                continue;
            }

            Schema::table($pivot, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable();
            });

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

            $idxName = strlen($pivot) > 18 ? substr($pivot, 0, 18).'_tenant_idx' : $pivot.'_tenant_idx';
            Schema::table($pivot, function (Blueprint $table) use ($idxName) {
                $table->index('tenant_id', $idxName);
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $pivot) {
            if (! Schema::hasTable($pivot) || ! Schema::hasColumn($pivot, 'tenant_id')) {
                continue;
            }
            $idxName = strlen($pivot) > 18 ? substr($pivot, 0, 18).'_tenant_idx' : $pivot.'_tenant_idx';
            Schema::table($pivot, function (Blueprint $table) use ($idxName) {
                $table->dropIndex($idxName);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
