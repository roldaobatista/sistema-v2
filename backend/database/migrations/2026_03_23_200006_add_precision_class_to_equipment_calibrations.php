<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_calibrations', function (Blueprint $table) {
            if (! Schema::hasColumn('equipment_calibrations', 'precision_class')) {
                $table->string('precision_class', 10)->nullable()->after('calibration_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('equipment_calibrations', function (Blueprint $table) {
            $table->dropColumn('precision_class');
        });
    }
};
