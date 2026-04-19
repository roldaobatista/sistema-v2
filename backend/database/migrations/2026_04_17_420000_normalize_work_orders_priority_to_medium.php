<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PROD-RA-06 — padroniza vocabulário de priority.
 *
 * central_* e padrão de mercado (JIRA/Linear/ServiceNow): low/medium/high/urgent.
 * work_orders usava 'normal' em vez de 'medium'. Normaliza UPDATE + default.
 *
 * Tabelas afetadas: work_orders, material_requests (mesma escala).
 * Outras tabelas com `priority` int não afetadas (escalas numéricas separadas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_orders') && Schema::hasColumn('work_orders', 'priority')) {
            DB::table('work_orders')->where('priority', 'normal')->update(['priority' => 'medium']);
        }

        if (Schema::hasTable('material_requests') && Schema::hasColumn('material_requests', 'priority')) {
            DB::table('material_requests')->where('priority', 'normal')->update(['priority' => 'medium']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('work_orders') && Schema::hasColumn('work_orders', 'priority')) {
            DB::table('work_orders')->where('priority', 'medium')->update(['priority' => 'normal']);
        }
        if (Schema::hasTable('material_requests') && Schema::hasColumn('material_requests', 'priority')) {
            DB::table('material_requests')->where('priority', 'medium')->update(['priority' => 'normal']);
        }
    }
};
