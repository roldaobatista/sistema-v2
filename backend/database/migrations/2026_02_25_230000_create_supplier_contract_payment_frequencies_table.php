<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_contract_payment_frequencies')) {
            return;
        }

        Schema::create('supplier_contract_payment_frequencies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id', 'supp_ctrt_pay_freq_tid_idx');
            $table->unique(['tenant_id', 'slug'], 'supp_ctrt_pay_freq_tid_slug_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_contract_payment_frequencies');
    }
};
