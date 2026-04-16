<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts_receivable', 'nosso_numero')) {
                $table->string('nosso_numero', 30)->nullable();
            }
            if (! Schema::hasColumn('accounts_receivable', 'numero_documento')) {
                $table->string('numero_documento', 30)->nullable();
            }
        });

        // Índices para matching CNAB eficiente
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $existingIndexes = collect(Schema::getIndexes('accounts_receivable'))
                ->pluck('name')
                ->toArray();

            if (! in_array('ar_cnab_match_idx', $existingIndexes)) {
                $table->index(['tenant_id', 'nosso_numero'], 'ar_cnab_match_idx');
            }
            if (! in_array('ar_cnab_doc_idx', $existingIndexes)) {
                $table->index(['tenant_id', 'numero_documento'], 'ar_cnab_doc_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->dropIndex('ar_cnab_match_idx');
            $table->dropIndex('ar_cnab_doc_idx');
            $table->dropColumn(['nosso_numero', 'numero_documento']);
        });
    }
};
