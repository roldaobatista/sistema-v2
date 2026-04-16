<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sync_conflict_logs')) {
            return;
        }
        Schema::create('sync_conflict_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('work_order_id')->index();
            $table->string('conflict_type', 50);
            $table->json('client_data')->nullable();
            $table->json('server_data')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('work_order_id')->references('id')->on('work_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflict_logs');
    }
};
