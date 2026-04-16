<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_clock_entries', function (Blueprint $table) {
            $table->string('employee_confirmation_hash', 64)->nullable()->after('record_hash');
            $table->timestamp('confirmed_at')->nullable()->after('employee_confirmation_hash');
            $table->string('confirmation_method', 20)->nullable()->after('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('time_clock_entries', function (Blueprint $table) {
            $table->dropColumn(['employee_confirmation_hash', 'confirmed_at', 'confirmation_method']);
        });
    }
};
