<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('crm_deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('code', 50);
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('planning');
                $table->string('priority')->default('medium');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->date('actual_start_date')->nullable();
                $table->date('actual_end_date')->nullable();
                $table->decimal('budget', 15, 2)->default(0);
                $table->decimal('spent', 15, 2)->default(0);
                $table->decimal('progress_percent', 5, 2)->default(0);
                $table->string('billing_type', 50)->default('fixed_price');
                $table->decimal('hourly_rate', 10, 2)->nullable();
                $table->json('tags')->nullable();
                $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'code'], 'prj_tenant_code_uq');
            });
        }

        if (Schema::hasTable('work_orders') && ! Schema::hasColumn('work_orders', 'project_id')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('work_orders') && Schema::hasColumn('work_orders', 'project_id')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            });
        }

        Schema::dropIfExists('projects');
    }
};
