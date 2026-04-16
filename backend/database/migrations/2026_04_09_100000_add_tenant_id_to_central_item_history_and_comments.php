<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Passo 1: Corrigir schema drift historico em producao.
        // Descoberto em 2026-04-10: producao tem coluna `central_item_id` mas
        // o codigo Laravel (AgendaItemHistory, AgendaItemComment) e todas as
        // demais migrations usam `agenda_item_id`. Esse rename foi aplicado
        // manualmente em prod no passado sem migration oficial, causando
        // quebra silenciosa das relacoes `.item()`.
        // Esta migration restaura o nome correto (no-op em dev/teste onde ja
        // esta certo) antes de seguir com o backfill.
        if (Schema::hasColumn('central_item_history', 'central_item_id')
            && ! Schema::hasColumn('central_item_history', 'agenda_item_id')) {
            Schema::table('central_item_history', function (Blueprint $table) {
                $table->renameColumn('central_item_id', 'agenda_item_id');
            });
        }

        if (Schema::hasColumn('central_item_comments', 'central_item_id')
            && ! Schema::hasColumn('central_item_comments', 'agenda_item_id')) {
            Schema::table('central_item_comments', function (Blueprint $table) {
                $table->renameColumn('central_item_id', 'agenda_item_id');
            });
        }

        // Passo 2: Adicionar coluna tenant_id (idempotente).
        if (! Schema::hasColumn('central_item_history', 'tenant_id')) {
            Schema::table('central_item_history', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        if (! Schema::hasColumn('central_item_comments', 'tenant_id')) {
            Schema::table('central_item_comments', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        // Passo 3: Backfill tenant_id a partir da central_items pai.
        DB::statement('
            UPDATE central_item_history
            SET tenant_id = (
                SELECT ci.tenant_id FROM central_items ci WHERE ci.id = central_item_history.agenda_item_id
            )
            WHERE tenant_id IS NULL
        ');

        DB::statement('
            UPDATE central_item_comments
            SET tenant_id = (
                SELECT ci.tenant_id FROM central_items ci WHERE ci.id = central_item_comments.agenda_item_id
            )
            WHERE tenant_id IS NULL
        ');
    }

    public function down(): void
    {
        if (Schema::hasColumn('central_item_history', 'tenant_id')) {
            Schema::table('central_item_history', function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasColumn('central_item_comments', 'tenant_id')) {
            Schema::table('central_item_comments', function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
