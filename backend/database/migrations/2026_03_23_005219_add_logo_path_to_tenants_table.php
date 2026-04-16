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
        // Conforme regras de produção do KALIBRIUM: NUNCA usar ->after() em migration nova.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
