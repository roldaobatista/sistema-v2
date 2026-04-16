<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('depreciation_logs')) {
            return;
        }

        Schema::create('depreciation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_record_id')->constrained('asset_records')->cascadeOnDelete();
            $table->date('reference_month');
            $table->decimal('depreciation_amount', 15, 2);
            $table->decimal('accumulated_before', 15, 2);
            $table->decimal('accumulated_after', 15, 2);
            $table->decimal('book_value_after', 15, 2);
            $table->string('method_used', 30);
            $table->unsignedInteger('ciap_installment_number')->nullable();
            $table->decimal('ciap_credit_value', 15, 2)->nullable();
            $table->string('generated_by', 20);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'asset_record_id', 'reference_month'],
                'depreciation_logs_tenant_asset_month_unique'
            );
            $table->index(['tenant_id', 'reference_month'], 'depreciation_logs_tenant_month_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_logs');
    }
};
