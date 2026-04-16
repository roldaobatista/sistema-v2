<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inmetro_owners') || Schema::hasColumn('inmetro_owners', 'enrichment_data')) {
            return;
        }

        Schema::table('inmetro_owners', function (Blueprint $table) {
            $table->json('enrichment_data')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inmetro_owners') || ! Schema::hasColumn('inmetro_owners', 'enrichment_data')) {
            return;
        }

        Schema::table('inmetro_owners', function (Blueprint $table) {
            $table->dropColumn('enrichment_data');
        });
    }
};
