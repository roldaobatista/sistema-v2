<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('time_clock_entries')) {
            return;
        }

        Schema::table('time_clock_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('time_clock_entries', 'break_start')) {
                $table->timestamp('break_start')->nullable();
            }
            if (! Schema::hasColumn('time_clock_entries', 'break_end')) {
                $table->timestamp('break_end')->nullable();
            }
            if (! Schema::hasColumn('time_clock_entries', 'break_latitude')) {
                $table->decimal('break_latitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('time_clock_entries', 'break_longitude')) {
                $table->decimal('break_longitude', 10, 7)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('time_clock_entries')) {
            return;
        }

        $columns = ['break_start', 'break_end', 'break_latitude', 'break_longitude'];
        foreach ($columns as $col) {
            if (Schema::hasColumn('time_clock_entries', $col)) {
                Schema::table('time_clock_entries', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
