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
        if (Schema::hasTable('work_order_events')) {
            if (! Schema::hasColumn('work_order_events', 'metadata')) {
                Schema::table('work_order_events', function (Blueprint $table) {
                    $table->json('metadata')->nullable()->after('longitude');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('work_order_events') && Schema::hasColumn('work_order_events', 'metadata')) {
            Schema::table('work_order_events', function (Blueprint $table) {
                $table->dropColumn('metadata');
            });
        }
    }
};
