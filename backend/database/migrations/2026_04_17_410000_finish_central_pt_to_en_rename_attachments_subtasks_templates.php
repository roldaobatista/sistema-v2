<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 7.2 — conclui PT→EN nas tabelas central_* (resíduos da Wave 6.7).
 *
 * PROD-RA-02 / GOV-RA-01 / DATA-RA-02 — central_templates:
 *   nome → name, categoria → category, ativo → is_active,
 *   default 'TAREFA' (PT upper) → 'task' (EN lower).
 *
 * PROD-RA-03 / GOV-RA-02 — central_subtasks:
 *   concluido → is_completed, ordem → sort_order.
 *
 * DATA-RA-01 — central_attachments:
 *   nome → name.
 *
 * PROD-RA-05 / GOV-RA-03 — central_items:
 *   drop legacy user_id (substituido por assignee_user_id/created_by_user_id)
 *   drop legacy completed (substituido por closed_at).
 *
 * Guards H3 hasTable/hasColumn em todos os passos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // central_attachments.nome → name
        if (Schema::hasTable('central_attachments') && Schema::hasColumn('central_attachments', 'nome')) {
            Schema::table('central_attachments', function (Blueprint $t) {
                $t->renameColumn('nome', 'name');
            });
        }

        // central_subtasks: concluido → is_completed, ordem → sort_order
        if (Schema::hasTable('central_subtasks')) {
            if (Schema::hasColumn('central_subtasks', 'concluido')) {
                Schema::table('central_subtasks', function (Blueprint $t) {
                    $t->renameColumn('concluido', 'is_completed');
                });
            }
            if (Schema::hasColumn('central_subtasks', 'ordem')) {
                Schema::table('central_subtasks', function (Blueprint $t) {
                    $t->renameColumn('ordem', 'sort_order');
                });
            }
        }

        // central_templates: nome, categoria, ativo
        if (Schema::hasTable('central_templates')) {
            if (Schema::hasColumn('central_templates', 'nome')) {
                Schema::table('central_templates', function (Blueprint $t) {
                    $t->renameColumn('nome', 'name');
                });
            }
            if (Schema::hasColumn('central_templates', 'categoria')) {
                Schema::table('central_templates', function (Blueprint $t) {
                    $t->renameColumn('categoria', 'category');
                });
            }
            if (Schema::hasColumn('central_templates', 'ativo')) {
                Schema::table('central_templates', function (Blueprint $t) {
                    $t->renameColumn('ativo', 'is_active');
                });
            }

            // Normaliza default 'TAREFA' → 'task' + UPDATE dos rows existentes
            if (Schema::hasColumn('central_templates', 'type')) {
                DB::table('central_templates')
                    ->where('type', 'TAREFA')
                    ->update(['type' => 'task']);
                DB::table('central_templates')
                    ->where('type', 'LEMBRETE')
                    ->update(['type' => 'reminder']);
                DB::table('central_templates')
                    ->where('type', 'APROVACAO')
                    ->update(['type' => 'approval']);
            }
        }

        // central_items: drop legacy user_id + completed
        if (Schema::hasTable('central_items')) {
            if (Schema::hasColumn('central_items', 'user_id')) {
                Schema::table('central_items', function (Blueprint $t) {
                    $t->dropColumn('user_id');
                });
            }
            if (Schema::hasColumn('central_items', 'completed')) {
                Schema::table('central_items', function (Blueprint $t) {
                    $t->dropColumn('completed');
                });
            }
        }
    }

    public function down(): void
    {
        // Revert parcial (renames) — drops são permanentes.
        if (Schema::hasTable('central_attachments') && Schema::hasColumn('central_attachments', 'name')) {
            Schema::table('central_attachments', fn (Blueprint $t) => $t->renameColumn('name', 'nome'));
        }
        if (Schema::hasTable('central_subtasks')) {
            if (Schema::hasColumn('central_subtasks', 'is_completed')) {
                Schema::table('central_subtasks', fn (Blueprint $t) => $t->renameColumn('is_completed', 'concluido'));
            }
            if (Schema::hasColumn('central_subtasks', 'sort_order')) {
                Schema::table('central_subtasks', fn (Blueprint $t) => $t->renameColumn('sort_order', 'ordem'));
            }
        }
        if (Schema::hasTable('central_templates')) {
            if (Schema::hasColumn('central_templates', 'name')) {
                Schema::table('central_templates', fn (Blueprint $t) => $t->renameColumn('name', 'nome'));
            }
            if (Schema::hasColumn('central_templates', 'category')) {
                Schema::table('central_templates', fn (Blueprint $t) => $t->renameColumn('category', 'categoria'));
            }
            if (Schema::hasColumn('central_templates', 'is_active')) {
                Schema::table('central_templates', fn (Blueprint $t) => $t->renameColumn('is_active', 'ativo'));
            }
        }
    }
};
