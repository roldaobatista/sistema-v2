<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_tickets')) {
            Schema::create('portal_tickets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('equipment_id')->nullable()->index();
                $table->string('ticket_number')->nullable();
                $table->string('subject');
                $table->text('description')->nullable();
                $table->string('priority')->default('normal');
                $table->string('status')->default('open');
                $table->string('category')->nullable();
                $table->string('source')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable()->index();
                $table->timestamp('resolved_at')->nullable();
                $table->string('qr_code')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
                $table->foreign('equipment_id')->references('id')->on('equipments')->onDelete('set null');
                $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_tickets');
    }
};
