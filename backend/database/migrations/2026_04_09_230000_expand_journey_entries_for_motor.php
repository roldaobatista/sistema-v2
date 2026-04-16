<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journey_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journey_entries', 'total_minutes_worked')) {
                $table->integer('total_minutes_worked')->default(0);
            }
            if (! Schema::hasColumn('journey_entries', 'total_minutes_overtime')) {
                $table->integer('total_minutes_overtime')->default(0);
            }
            if (! Schema::hasColumn('journey_entries', 'total_minutes_travel')) {
                $table->integer('total_minutes_travel')->default(0);
            }
            if (! Schema::hasColumn('journey_entries', 'total_minutes_wait')) {
                $table->integer('total_minutes_wait')->default(0);
            }
            if (! Schema::hasColumn('journey_entries', 'total_minutes_break')) {
                $table->integer('total_minutes_break')->default(0);
            }
            if (! Schema::hasColumn('journey_entries', 'total_minutes_overnight')) {
                $table->integer('total_minutes_overnight')->default(0);
            }
            if (! Schema::hasColumn('journey_entries', 'total_minutes_oncall')) {
                $table->integer('total_minutes_oncall')->default(0);
            }
            if (! Schema::hasColumn('journey_entries', 'operational_approval_status')) {
                $table->string('operational_approval_status')->default('pending');
            }
            if (! Schema::hasColumn('journey_entries', 'operational_approver_id')) {
                $table->foreignId('operational_approver_id')->nullable()->constrained('users');
            }
            if (! Schema::hasColumn('journey_entries', 'operational_approved_at')) {
                $table->timestamp('operational_approved_at')->nullable();
            }
            if (! Schema::hasColumn('journey_entries', 'hr_approval_status')) {
                $table->string('hr_approval_status')->default('pending');
            }
            if (! Schema::hasColumn('journey_entries', 'hr_approver_id')) {
                $table->foreignId('hr_approver_id')->nullable()->constrained('users');
            }
            if (! Schema::hasColumn('journey_entries', 'hr_approved_at')) {
                $table->timestamp('hr_approved_at')->nullable();
            }
            if (! Schema::hasColumn('journey_entries', 'is_closed')) {
                $table->boolean('is_closed')->default(false);
            }
            if (! Schema::hasColumn('journey_entries', 'regime_type')) {
                $table->string('regime_type')->default('clt_mensal');
            }
            if (! Schema::hasColumn('journey_entries', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('journey_entries', function (Blueprint $table) {
            $cols = [
                'total_minutes_worked', 'total_minutes_overtime', 'total_minutes_travel',
                'total_minutes_wait', 'total_minutes_break', 'total_minutes_overnight',
                'total_minutes_oncall', 'operational_approval_status', 'operational_approver_id',
                'operational_approved_at', 'hr_approval_status', 'hr_approver_id',
                'hr_approved_at', 'is_closed', 'regime_type',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('journey_entries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
