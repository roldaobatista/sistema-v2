<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $map = [
        'nome' => 'name',
        'ativo' => 'active',
        'evento_trigger' => 'event_trigger',
        'tipo_item' => 'item_type',
        'prioridade_minima' => 'min_priority',
        'acao_tipo' => 'action_type',
        'acao_config' => 'action_config',
        'role_alvo' => 'target_role',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('central_rules')) {
            return;
        }
        Schema::table('central_rules', function (Blueprint $table) {
            foreach ($this->map as $from => $to) {
                if (Schema::hasColumn('central_rules', $from) && ! Schema::hasColumn('central_rules', $to)) {
                    $table->renameColumn($from, $to);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('central_rules')) {
            return;
        }
        Schema::table('central_rules', function (Blueprint $table) {
            foreach ($this->map as $from => $to) {
                if (Schema::hasColumn('central_rules', $to) && ! Schema::hasColumn('central_rules', $from)) {
                    $table->renameColumn($to, $from);
                }
            }
        });
    }
};
