<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sec-07 (Re-auditoria Camada 1 r3): adiciona users.password_changed_at
 * para permitir politicas de rotacao de senha (OWASP ASVS V2.1.10) e
 * invalidacao de sessoes web baseadas em timestamp.
 *
 * Usuarios existentes ficam com NULL (backfill opcional fora deste
 * escopo — indica "nunca trocou via fluxo atual").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('password_changed_at')->nullable()->after('password');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('password_changed_at');
            });
        }
    }
};
