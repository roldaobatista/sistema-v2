<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_calculations')) {
            Schema::create('tax_calculations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('work_order_id')->nullable()->index();
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
                $table->string('tax_type');
                $table->decimal('base_amount', 15, 2)->default(0);
                $table->decimal('rate', 10, 4)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->string('regime')->nullable();
                $table->unsignedBigInteger('calculated_by')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('set null');
                $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
                $table->foreign('calculated_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_calculations');
    }
};
