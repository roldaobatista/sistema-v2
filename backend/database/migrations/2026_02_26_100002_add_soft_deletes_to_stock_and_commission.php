<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona deleted_at em stock_movements e commission_events para rastreabilidade.
 */
return new class extends Migration
{
    private array $tables = ['stock_movements', 'commission_events'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
