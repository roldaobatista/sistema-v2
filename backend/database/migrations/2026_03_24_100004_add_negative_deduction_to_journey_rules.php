<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journey_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('journey_rules', 'allow_negative_hour_bank_deduction')) {
                $table->boolean('allow_negative_hour_bank_deduction')->default(false)->after('hour_bank_expiry_months');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journey_rules', function (Blueprint $table) {
            if (Schema::hasColumn('journey_rules', 'allow_negative_hour_bank_deduction')) {
                $table->dropColumn('allow_negative_hour_bank_deduction');
            }
        });
    }
};
