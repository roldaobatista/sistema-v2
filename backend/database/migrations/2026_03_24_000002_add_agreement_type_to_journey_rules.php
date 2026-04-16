<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journey_rules')) {
            return;
        }

        Schema::table('journey_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('journey_rules', 'agreement_type')) {
                $table->string('agreement_type', 20)->default('individual')->after('hour_bank_expiry_months');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('journey_rules') || ! Schema::hasColumn('journey_rules', 'agreement_type')) {
            return;
        }

        Schema::table('journey_rules', function (Blueprint $table) {
            $table->dropColumn('agreement_type');
        });
    }
};
