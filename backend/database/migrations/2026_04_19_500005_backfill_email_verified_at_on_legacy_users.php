<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * sec-26 (Re-auditoria Camada 1 r3): backfill de email_verified_at para
 * usuários legados.
 *
 * Novo check em AuthController::login rejeita login quando
 * email_verified_at=NULL. Usuários criados ANTES do deploy deste fix
 * possuem email_verified_at=NULL por nunca terem confirmado email
 * (histórico). Sem backfill, todos seriam bloqueados em prod.
 *
 * Estratégia: seta email_verified_at=created_at para qualquer user com
 * email_verified_at IS NULL. Assume-se que usuários legados que lograram
 * logar no sistema em algum momento estavam em uso legítimo. A nova
 * regra aplica-se apenas a registrations DEPOIS desta migration.
 *
 * Idempotente: re-executar não altera users já com timestamp setado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'email_verified_at')) {
            return;
        }

        DB::table('users')
            ->whereNull('email_verified_at')
            ->update([
                'email_verified_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        // No-op: não faz sentido reverter verificação de email em bulk;
        // usuários continuam autenticáveis com email verificado, o que é
        // o estado seguro.
    }
};
