<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private array $renames = [
        'central_items' => [
            'tipo' => 'type',
            'origem' => 'source',
            'ref_tipo' => 'ref_type',
            'titulo' => 'title',
            'descricao_curta' => 'short_description',
            'responsavel_user_id' => 'assignee_user_id',
            'criado_por_user_id' => 'created_by_user_id',
            'prioridade' => 'priority',
            'visibilidade' => 'visibility',
            'contexto' => 'context',
        ],
        'central_rules' => [
            'descricao' => 'description',
            'responsavel_user_id' => 'assignee_user_id',
        ],
        'central_subtasks' => [
            'titulo' => 'title',
        ],
        'central_time_entries' => [
            'descricao' => 'description',
        ],
        'central_templates' => [
            'descricao' => 'description',
            'tipo' => 'type',
            'prioridade' => 'priority',
            'visibilidade' => 'visibility',
        ],
    ];

    public function up(): void
    {
        foreach ($this->renames as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table, $cols) {
                foreach ($cols as $from => $to) {
                    if (Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
                        $blueprint->renameColumn($from, $to);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->renames as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table, $cols) {
                foreach ($cols as $from => $to) {
                    if (Schema::hasColumn($table, $to) && ! Schema::hasColumn($table, $from)) {
                        $blueprint->renameColumn($to, $from);
                    }
                }
            });
        }
    }
};
