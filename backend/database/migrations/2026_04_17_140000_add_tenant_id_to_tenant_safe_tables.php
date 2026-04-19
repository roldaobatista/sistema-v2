<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2A — DATA-003 + SEC-009
 *
 * Adiciona `tenant_id` (NULLABLE) + FK + índice às tabelas standalone que NÃO
 * eram filhas diretas de uma entidade multi-tenant e estavam expostas a
 * vazamento entre tenants (violação H2 do Iron Protocol).
 *
 * Tabelas alvo (Categoria 1 — DEVE ter tenant_id direto):
 *  - mobile_notifications, qr_scans, asset_tag_scans, biometric_configs
 *  - warehouse_stocks, webhook_logs
 *  - user_favorites, user_preferences, user_sessions
 *  - operational_snapshots, inmetro_history, inventory_tables_v3
 *
 * NÃO incluídas:
 *  - Categoria 2 (child tables): herdam tenant via parent que já possui
 *    BelongsToTenant (ex.: stock_transfer_items, purchase_quote_items, etc.).
 *  - Categoria 3 (system-wide): `marketplace_partners` é catálogo público
 *    de parceiros (lookup compartilhado entre todos os tenants) — ver
 *    docs/TECHNICAL-DECISIONS.md.
 *
 * NOT NULL + backfill virão em Wave 2B (após job popular dados existentes).
 * Esta migration cria APENAS a estrutura (NULLABLE) — segura para deploy
 * sem downtime e sem quebrar registros existentes.
 *
 * Guards H3 (Iron Protocol): hasTable + hasColumn idempotentes.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'mobile_notifications',
        'qr_scans',
        'asset_tag_scans',
        'biometric_configs',
        'warehouse_stocks',
        'webhook_logs',
        'user_favorites',
        'user_preferences',
        'user_sessions',
        'operational_snapshots',
        'inmetro_history',
        'inventory_tables_v3',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('tenant_id')->nullable()->after('id');
            });

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->index('tenant_id', "{$table}_tenant_id_idx");
            });

            // FK pode falhar em SQLite (testes) se a tabela tenants ainda
            // não foi criada na ordem certa — proteger.
            if (Schema::hasTable('tenants')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreign('tenant_id')
                        ->references('id')->on('tenants')
                        ->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                try {
                    $t->dropForeign(['tenant_id']);
                } catch (Throwable $e) {
                    // FK pode não existir (SQLite) — ignorar.
                }

                try {
                    $t->dropIndex("{$table}_tenant_id_idx");
                } catch (Throwable $e) {
                    // Index pode não existir — ignorar.
                }
                $t->dropColumn('tenant_id');
            });
        }
    }
};
