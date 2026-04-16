<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standard_weights', function (Blueprint $table) {
            $table->string('laboratory_accreditation', 100)->nullable()->after('laboratory')
                ->comment('Acreditação do laboratório emissor (ex: RBC/Cgcre CRL-XXXX)');
            $table->string('traceability_chain', 500)->nullable()->after('laboratory_accreditation')
                ->comment('Cadeia de rastreabilidade metrológica resumida');
        });
    }

    public function down(): void
    {
        Schema::table('standard_weights', function (Blueprint $table) {
            $table->dropColumn(['laboratory_accreditation', 'traceability_chain']);
        });
    }
};
