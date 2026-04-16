<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_categories')) {
            Schema::create('ticket_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('sla_policy_id')->nullable()->constrained('sla_policies')->nullOnDelete();
                $table->string('default_priority')->default('medium');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('escalation_rules')) {
            Schema::create('escalation_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sla_policy_id')->constrained('sla_policies')->cascadeOnDelete();
                $table->string('name');
                $table->integer('trigger_minutes')->comment('Minutes after SLA violation to trigger');
                $table->string('action_type')->comment('notify, reassign, change_priority');
                $table->json('action_payload')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sla_violations')) {
            Schema::create('sla_violations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('portal_ticket_id')->constrained('portal_tickets')->cascadeOnDelete();
                $table->foreignId('sla_policy_id')->constrained('sla_policies')->cascadeOnDelete();
                $table->string('violation_type')->comment('response_time, resolution_time');
                $table->timestamp('violated_at');
                $table->integer('minutes_exceeded');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_violations');
        Schema::dropIfExists('escalation_rules');
        Schema::dropIfExists('ticket_categories');
    }
};
