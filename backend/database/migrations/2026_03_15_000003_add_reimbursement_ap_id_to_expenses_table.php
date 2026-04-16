<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'reimbursement_ap_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                // foreignId, nullable
                $table->unsignedBigInteger('reimbursement_ap_id')->nullable();

                // Sem constrain com delete cascade pra nao dar dor de cabeca se o contas a pagar apagar
                // ou com constrain. Mas como as outras FK nao tem constraint literal para isso, deixo soft
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'reimbursement_ap_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('reimbursement_ap_id');
            });
        }
    }
};
