<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_orders')) {
            Schema::table('work_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('work_orders', 'return_started_at')) {
                    $table->timestamp('return_started_at')->nullable();
                }
                if (! Schema::hasColumn('work_orders', 'return_arrived_at')) {
                    $table->timestamp('return_arrived_at')->nullable();
                }
                if (! Schema::hasColumn('work_orders', 'return_duration_minutes')) {
                    $table->unsignedInteger('return_duration_minutes')->nullable();
                }
                if (! Schema::hasColumn('work_orders', 'return_destination')) {
                    $table->string('return_destination', 50)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('work_orders')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $cols = ['return_started_at', 'return_arrived_at', 'return_duration_minutes', 'return_destination'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('work_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
