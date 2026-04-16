<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journey_entries')) {
            return;
        }

        Schema::table('journey_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journey_entries', 'overtime_limit_exceeded')) {
                $table->boolean('overtime_limit_exceeded')->default(false)->after('hour_bank_balance');
            }
            if (! Schema::hasColumn('journey_entries', 'tolerance_applied')) {
                $table->boolean('tolerance_applied')->default(false)->after('overtime_limit_exceeded');
            }
            if (! Schema::hasColumn('journey_entries', 'break_compliance')) {
                $table->string('break_compliance', 20)->nullable()->after('tolerance_applied');
            }
            if (! Schema::hasColumn('journey_entries', 'inter_shift_hours')) {
                $table->decimal('inter_shift_hours', 5, 2)->nullable()->after('break_compliance');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('journey_entries')) {
            return;
        }

        $columns = ['overtime_limit_exceeded', 'tolerance_applied', 'break_compliance', 'inter_shift_hours'];
        foreach ($columns as $col) {
            if (Schema::hasColumn('journey_entries', $col)) {
                Schema::table('journey_entries', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
