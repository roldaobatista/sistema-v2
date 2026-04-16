<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_records')) {
            return;
        }

        Schema::create('asset_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 50);
            $table->date('acquisition_date');
            $table->decimal('acquisition_value', 15, 2);
            $table->decimal('residual_value', 15, 2)->default(0);
            $table->unsignedInteger('useful_life_months');
            $table->string('depreciation_method', 30);
            $table->decimal('depreciation_rate', 8, 4)->default(0);
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);
            $table->decimal('current_book_value', 15, 2)->default(0);
            $table->string('status', 30)->default('active');
            $table->string('location')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nf_number', 50)->nullable();
            $table->string('nf_serie', 10)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('fleet_vehicle_id')->nullable()->constrained('fleet_vehicles')->nullOnDelete();
            $table->string('ciap_credit_type', 20)->nullable();
            $table->unsignedInteger('ciap_total_installments')->nullable();
            $table->unsignedInteger('ciap_installments_taken')->nullable()->default(0);
            $table->date('last_depreciation_at')->nullable();
            $table->date('disposed_at')->nullable();
            $table->string('disposal_reason', 20)->nullable();
            $table->decimal('disposal_value', 15, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'asset_records_tenant_code_unique');
            $table->index(['tenant_id', 'category'], 'asset_records_tenant_category_idx');
            $table->index(['tenant_id', 'status'], 'asset_records_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_records');
    }
};
