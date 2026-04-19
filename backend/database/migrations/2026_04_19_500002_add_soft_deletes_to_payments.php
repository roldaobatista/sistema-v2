<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-auditoria Camada 1 2026-04-19 — finding data-03.
 *
 * `payments` é registro de baixa financeira (AR/AP). Deleção física deixa
 * registro órfão sem rastro de auditoria e, pior, fica indistinguível de
 * payment que nunca existiu. SoftDeletes preserva a linha com `deleted_at`.
 *
 * Integridade referencial do polimórfico `payable_type`/`payable_id` não
 * pode ser imposta no banco (MySQL não permite FK em coluna polimórfica).
 * A responsabilidade fica na aplicação: deletar `AccountReceivable` deve
 * cascatear soft-delete via `$receivable->payments()->delete()`.
 *
 * Idempotente: guarda `hasColumn` protege re-execução.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (Schema::hasColumn('payments', 'deleted_at')) {
            return;
        }

        Schema::table('payments', function (Blueprint $t): void {
            $t->softDeletes();
            $t->index('deleted_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (! Schema::hasColumn('payments', 'deleted_at')) {
            return;
        }

        Schema::table('payments', function (Blueprint $t): void {
            $t->dropIndex(['deleted_at']);
            $t->dropSoftDeletes();
        });
    }
};
