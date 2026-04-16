<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dateTime('client_accepted_at')->nullable()->after('will_emit_complementary_report');
            $table->string('client_accepted_by', 255)->nullable()->after('client_accepted_at');
            $table->string('applicable_procedure', 500)->nullable()->after('calibration_scope_notes');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn([
                'client_accepted_at',
                'client_accepted_by',
                'applicable_procedure',
            ]);
        });
    }
};
