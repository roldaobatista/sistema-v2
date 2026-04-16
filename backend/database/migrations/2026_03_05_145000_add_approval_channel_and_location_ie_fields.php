<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GAP-1: approval_channel no Quote (US-ORC-05)
        Schema::table('quotes', function (Blueprint $table) {
            if (! Schema::hasColumn('quotes', 'approval_channel')) {
                $table->string('approval_channel', 30)->nullable()
                    ->comment('online|whatsapp|phone|presential');
            }
            if (! Schema::hasColumn('quotes', 'approval_notes')) {
                $table->text('approval_notes')->nullable()
                    ->comment('Observações sobre a aprovação');
            }
            if (! Schema::hasColumn('quotes', 'approved_by_name')) {
                $table->string('approved_by_name', 100)->nullable()
                    ->comment('Nome de quem aprovou (quando externo)');
            }
        });

        // GAP-2: inscricao_estadual no CustomerLocation (US-CLI-02)
        Schema::table('customer_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_locations', 'inscricao_estadual')) {
                $table->string('inscricao_estadual', 20)->nullable()
                    ->comment('IE diferente por endereço (produtor rural)');
            }
            if (! Schema::hasColumn('customer_locations', 'nome_propriedade')) {
                $table->string('nome_propriedade', 150)->nullable()
                    ->comment('Nome da fazenda/filial');
            }
            if (! Schema::hasColumn('customer_locations', 'tipo')) {
                $table->string('tipo', 20)->nullable()->default('principal')
                    ->comment('principal|filial|fazenda|obra');
            }
            if (! Schema::hasColumn('customer_locations', 'endereco')) {
                $table->string('endereco', 255)->nullable();
            }
            if (! Schema::hasColumn('customer_locations', 'bairro')) {
                $table->string('bairro', 100)->nullable();
            }
            if (! Schema::hasColumn('customer_locations', 'cidade')) {
                $table->string('cidade', 100)->nullable();
            }
            if (! Schema::hasColumn('customer_locations', 'uf')) {
                $table->string('uf', 2)->nullable();
            }
            if (! Schema::hasColumn('customer_locations', 'cep')) {
                $table->string('cep', 10)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $cols = ['approval_channel', 'approval_notes', 'approved_by_name'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('quotes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('customer_locations', function (Blueprint $table) {
            $cols = ['inscricao_estadual', 'nome_propriedade', 'tipo', 'endereco', 'bairro', 'cidade', 'uf', 'cep'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('customer_locations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
