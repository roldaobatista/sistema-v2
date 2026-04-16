<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qr_scans')) {
            Schema::create('qr_scans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('work_order_id')->index();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('scanned_at');
                $table->timestamps();

                $table->foreign('work_order_id')->references('id')->on('work_orders')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_scans');
    }
};
