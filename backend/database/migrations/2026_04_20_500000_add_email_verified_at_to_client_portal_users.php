<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sec-portal-login-no-email-verification (Camada 1 r4 Batch C — S3)
 *
 * `App\Models\ClientPortalUser` já expõe `email_verified_at` em $fillable/$casts,
 * mas a coluna física não existia — login do portal nunca pôde enforçar verificação
 * de e-mail. Esta migration adiciona a coluna com guard hasColumn para ser idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_portal_users')) {
            return;
        }

        if (! Schema::hasColumn('client_portal_users', 'email_verified_at')) {
            Schema::table('client_portal_users', function (Blueprint $table) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_portal_users')) {
            return;
        }

        if (Schema::hasColumn('client_portal_users', 'email_verified_at')) {
            Schema::table('client_portal_users', function (Blueprint $table) {
                $table->dropColumn('email_verified_at');
            });
        }
    }
};
