<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-RA-06 — invalida backup_codes legados em user_2fa.
 *
 * Antes desta wave, TwoFactorAuth.backup_codes era armazenado com cast
 * `encrypted:array` (reversivel via APP_KEY). Novo padrao e hash bcrypt
 * irreversivel com verificacao via Hash::check.
 *
 * Registros existentes contem payload criptografado que nao pode ser
 * convertido em hash sem conhecer o plain text (e o plain text ja foi
 * entregue ao usuario uma unica vez). Solucao: setar backup_codes = null
 * para todos os user_2fa, forcando o usuario a regenerar os codes via
 * reativacao do 2FA.
 *
 * Idempotente: se a coluna ja esta null, o UPDATE nao causa dano.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_2fa') || ! Schema::hasColumn('user_2fa', 'backup_codes')) {
            return;
        }

        DB::table('user_2fa')->whereNotNull('backup_codes')->update(['backup_codes' => null]);
    }

    public function down(): void
    {
        // No-op: nao ha como restaurar backup_codes criptografados que foram substituidos.
    }
};
