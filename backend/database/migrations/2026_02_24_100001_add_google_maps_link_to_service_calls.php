<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_calls') && ! Schema::hasColumn('service_calls', 'google_maps_link')) {
            Schema::table('service_calls', function (Blueprint $table) {
                $table->string('google_maps_link', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('service_calls', 'google_maps_link')) {
            Schema::table('service_calls', function (Blueprint $table) {
                $table->dropColumn('google_maps_link');
            });
        }
    }
};
