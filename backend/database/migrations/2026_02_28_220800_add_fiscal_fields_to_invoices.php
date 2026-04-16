<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'fiscal_status')) {
                $table->string('fiscal_status')->nullable()->after('status')
                    ->comment('null=não iniciado, emitting=emitindo, emitted=emitida, failed=falhou');
            }
            if (! Schema::hasColumn('invoices', 'fiscal_note_key')) {
                $table->string('fiscal_note_key')->nullable()->after('fiscal_status')
                    ->comment('Chave/referência retornada pela API fiscal após emissão');
            }
            if (! Schema::hasColumn('invoices', 'fiscal_emitted_at')) {
                $table->timestamp('fiscal_emitted_at')->nullable()->after('fiscal_note_key');
            }
            if (! Schema::hasColumn('invoices', 'fiscal_error')) {
                $table->text('fiscal_error')->nullable()->after('fiscal_emitted_at')
                    ->comment('Última mensagem de erro da emissão fiscal');
            }
            if (! Schema::hasColumn('invoices', 'discount')) {
                $table->decimal('discount', 12, 2)->default(0)->after('total')
                    ->comment('Valor do desconto aplicado na OS (para reconciliação com itens)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(array_filter(
                ['fiscal_status', 'fiscal_note_key', 'fiscal_emitted_at', 'fiscal_error', 'discount'],
                fn ($col) => Schema::hasColumn('invoices', $col)
            ));
        });
    }
};
