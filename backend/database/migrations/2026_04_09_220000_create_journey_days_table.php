<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journey_days')) {
            return;
        }

        Schema::create('journey_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('reference_date');
            $table->string('regime_type')->default('clt_mensal');
            $table->integer('total_minutes_worked')->default(0);
            $table->integer('total_minutes_overtime')->default(0);
            $table->integer('total_minutes_travel')->default(0);
            $table->integer('total_minutes_wait')->default(0);
            $table->integer('total_minutes_break')->default(0);
            $table->integer('total_minutes_overnight')->default(0);
            $table->integer('total_minutes_oncall')->default(0);
            $table->string('operational_approval_status')->default('pending');
            $table->foreignId('operational_approver_id')->nullable()->constrained('users');
            $table->timestamp('operational_approved_at')->nullable();
            $table->string('hr_approval_status')->default('pending');
            $table->foreignId('hr_approver_id')->nullable()->constrained('users');
            $table->timestamp('hr_approved_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'user_id', 'reference_date']);
            $table->index(['tenant_id', 'reference_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_days');
    }
};
