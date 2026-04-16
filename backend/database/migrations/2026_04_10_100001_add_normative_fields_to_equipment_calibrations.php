<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_calibrations', function (Blueprint $table) {
            $table->dateTime('calibration_started_at')->nullable()->after('calibration_date');
            $table->dateTime('calibration_completed_at')->nullable()->after('calibration_started_at');

            $table->text('condition_as_found')->nullable()->after('after_adjustment_data');
            $table->text('condition_as_left')->nullable()->after('condition_as_found');

            $table->boolean('adjustment_performed')->default(false)->after('condition_as_left');

            $table->unsignedBigInteger('accreditation_scope_id')->nullable()->after('scope_declaration');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_calibrations', function (Blueprint $table) {
            $table->dropColumn([
                'calibration_started_at',
                'calibration_completed_at',
                'condition_as_found',
                'condition_as_left',
                'adjustment_performed',
                'accreditation_scope_id',
            ]);
        });
    }
};
