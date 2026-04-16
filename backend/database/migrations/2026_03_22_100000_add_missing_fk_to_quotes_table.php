<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona colunas e foreign keys faltantes na tabela quotes.
 *
 * - opportunity_id → deals (coluna já existe, FK faltante)
 * - level2_approved_by → users (coluna já existe, FK faltante)
 * - created_by → users (coluna e FK faltantes)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Adiciona coluna created_by se não existir ──
        if (! Schema::hasColumn('quotes', 'created_by')) {
            Schema::table('quotes', function (Blueprint $t) {
                $t->unsignedBigInteger('created_by')->nullable()->after('seller_id');
            });
        }

        // ── FK: opportunity_id → deals ──
        if (Schema::hasTable('deals') && Schema::hasColumn('quotes', 'opportunity_id')) {
            // Verifica se a FK já existe para não duplicar
            try {
                Schema::table('quotes', function (Blueprint $t) {
                    $t->foreign('opportunity_id')
                        ->references('id')
                        ->on('deals')
                        ->nullOnDelete();
                });
            } catch (Throwable $e) {
                if (! str_contains($e->getMessage(), 'Duplicate') && ! str_contains($e->getMessage(), 'already exists')) {
                    throw $e;
                }
            }
        }

        // ── FK: level2_approved_by → users ──
        if (Schema::hasTable('users') && Schema::hasColumn('quotes', 'level2_approved_by')) {
            try {
                Schema::table('quotes', function (Blueprint $t) {
                    $t->foreign('level2_approved_by')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            } catch (Throwable $e) {
                if (! str_contains($e->getMessage(), 'Duplicate') && ! str_contains($e->getMessage(), 'already exists')) {
                    throw $e;
                }
            }
        }

        // ── FK: created_by → users ──
        if (Schema::hasTable('users') && Schema::hasColumn('quotes', 'created_by')) {
            try {
                Schema::table('quotes', function (Blueprint $t) {
                    $t->foreign('created_by')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            } catch (Throwable $e) {
                if (! str_contains($e->getMessage(), 'Duplicate') && ! str_contains($e->getMessage(), 'already exists')) {
                    throw $e;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $t) {
            // Remove FKs de forma segura
            $fks = [
                'quotes_opportunity_id_foreign' => 'opportunity_id',
                'quotes_level2_approved_by_foreign' => 'level2_approved_by',
                'quotes_created_by_foreign' => 'created_by',
            ];

            foreach ($fks as $fkName => $column) {
                try {
                    $t->dropForeign([$column]);
                } catch (Throwable $e) {
                    // FK pode não existir — idempotente
                }
            }
        });

        // Remove coluna created_by se existir
        if (Schema::hasColumn('quotes', 'created_by')) {
            Schema::table('quotes', function (Blueprint $t) {
                $t->dropColumn('created_by');
            });
        }
    }
};
