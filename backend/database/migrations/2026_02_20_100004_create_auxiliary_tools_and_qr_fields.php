<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('auxiliary_tools')) {
            Schema::create('auxiliary_tools', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('serial_number')->nullable();
                $table->string('type')->nullable(); // termometro, higrometro, nivel, etc.
                $table->date('calibration_due_date')->nullable();
                $table->date('last_calibration_date')->nullable();
                $table->string('certificate_number')->nullable();
                $table->string('status')->default('active'); // active, in_calibration, out_of_service
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Add public_qr_hash to equipments for 2.16
        if (Schema::hasTable('equipments') && ! Schema::hasColumn('equipments', 'public_qr_hash')) {
            Schema::table('equipments', function (Blueprint $table) {
                $table->string('public_qr_hash')->nullable()->unique();
            });
        }

        // Add icp_signature_status to calibrations for 2.15
        if (Schema::hasTable('equipment_calibrations') && ! Schema::hasColumn('equipment_calibrations', 'icp_signature_status')) {
            Schema::table('equipment_calibrations', function (Blueprint $table) {
                $table->string('icp_signature_status')->nullable()->default(null);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auxiliary_tools');

        if (Schema::hasTable('equipments') && Schema::hasColumn('equipments', 'public_qr_hash')) {
            Schema::table('equipments', function (Blueprint $table) {
                $table->dropColumn('public_qr_hash');
            });
        }

        if (Schema::hasTable('equipment_calibrations') && Schema::hasColumn('equipment_calibrations', 'icp_signature_status')) {
            Schema::table('equipment_calibrations', function (Blueprint $table) {
                $table->dropColumn('icp_signature_status');
            });
        }
    }
};
