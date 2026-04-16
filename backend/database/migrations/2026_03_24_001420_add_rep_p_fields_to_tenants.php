<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('rep_p_program_name', 100)->default('Kalibrium ERP');
            $table->string('rep_p_version', 20)->default('1.0.0');
            $table->string('rep_p_developer_name', 100)->default('Kalibrium Sistemas');
            $table->string('rep_p_developer_cnpj', 14)->nullable();
            $table->string('timezone', 50)->default('America/Sao_Paulo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'rep_p_program_name',
                'rep_p_version',
                'rep_p_developer_name',
                'rep_p_developer_cnpj',
                'timezone',
            ]);
        });
    }
};
