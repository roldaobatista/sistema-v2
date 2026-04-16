<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calibration_readings', function (Blueprint $table) {
            $table->decimal('max_permissible_error', 12, 6)->nullable()->after('correction');
            $table->boolean('ema_conforms')->nullable()->after('max_permissible_error');
        });
    }

    public function down(): void
    {
        Schema::table('calibration_readings', function (Blueprint $table) {
            $table->dropColumn(['max_permissible_error', 'ema_conforms']);
        });
    }
};
