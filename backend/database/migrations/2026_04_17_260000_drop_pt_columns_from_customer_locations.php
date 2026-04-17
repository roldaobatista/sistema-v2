<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $cols = [
        'inscricao_estadual',
        'nome_propriedade',
        'tipo',
        'endereco',
        'bairro',
        'cidade',
        'uf',
        'cep',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('customer_locations')) {
            return;
        }

        Schema::table('customer_locations', function (Blueprint $table) {
            foreach ($this->cols as $col) {
                if (Schema::hasColumn('customer_locations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_locations')) {
            return;
        }

        Schema::table('customer_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_locations', 'inscricao_estadual')) {
                $table->string('inscricao_estadual', 20)->nullable();
            }
            if (! Schema::hasColumn('customer_locations', 'nome_propriedade')) {
                $table->string('nome_propriedade', 150)->nullable();
            }
            if (! Schema::hasColumn('customer_locations', 'tipo')) {
                $table->string('tipo', 20)->default('principal')->nullable();
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
};
