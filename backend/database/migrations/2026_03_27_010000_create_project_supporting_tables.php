<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_milestones')) {
            Schema::create('project_milestones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->string('name');
                $table->string('status')->default('pending');
                $table->unsignedInteger('order');
                $table->date('planned_start')->nullable();
                $table->date('planned_end')->nullable();
                $table->date('actual_start')->nullable();
                $table->date('actual_end')->nullable();
                $table->decimal('billing_value', 15, 2)->nullable();
                $table->decimal('billing_percent', 5, 2)->nullable();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->decimal('weight', 5, 2)->default(1);
                $table->json('dependencies')->nullable();
                $table->text('deliverables')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'project_id'], 'project_milestones_tenant_project_idx');
            });
        }

        if (! Schema::hasTable('project_resources')) {
            Schema::create('project_resources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 100);
                $table->decimal('allocation_percent', 5, 2);
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('hourly_rate', 10, 2)->nullable();
                $table->decimal('total_hours_planned', 10, 2)->nullable();
                $table->decimal('total_hours_logged', 10, 2)->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'project_id'], 'project_resources_tenant_project_idx');
            });
        }

        if (! Schema::hasTable('project_time_entries')) {
            Schema::create('project_time_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('project_resource_id')->constrained('project_resources')->cascadeOnDelete();
                $table->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
                $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
                $table->date('date');
                $table->decimal('hours', 5, 2);
                $table->text('description')->nullable();
                $table->boolean('billable')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'project_id'], 'project_time_entries_tenant_project_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_time_entries');
        Schema::dropIfExists('project_resources');
        Schema::dropIfExists('project_milestones');
    }
};
