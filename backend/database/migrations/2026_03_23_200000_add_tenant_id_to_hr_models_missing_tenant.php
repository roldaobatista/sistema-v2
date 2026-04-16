<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['onboarding_checklist_items', 'skill_requirements', 'user_skills'];

        // Obter o primeiro tenant para preencher registros órfãos
        $defaultTenantId = DB::table('tenants')->value('id');

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'tenant_id')) {
                // 1. Adicionar coluna nullable primeiro
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('tenant_id')->nullable()->after('id');
                });

                // 2. Popular registros existentes com o tenant padrão
                if ($defaultTenantId) {
                    DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
                }

                // 3. Remover registros órfãos que não puderam ser associados
                DB::table($table)->whereNull('tenant_id')->delete();

                // 4. Tornar NOT NULL e adicionar FK
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('tenant_id')->nullable(false)->change();
                    $t->foreign('tenant_id')
                        ->references('id')
                        ->on('tenants')
                        ->onUpdate('cascade')
                        ->onDelete('cascade');
                    $t->index('tenant_id');
                });
            }
        }
    }

    public function down(): void
    {
        $tables = ['onboarding_checklist_items', 'skill_requirements', 'user_skills'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropForeign(["{$table}_tenant_id_foreign"]);
                    $t->dropIndex(["{$table}_tenant_id_index"]);
                    $t->dropColumn('tenant_id');
                });
            }
        }
    }
};
