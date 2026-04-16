<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seal_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('work_order_id')->index();
            $table->unsignedBigInteger('equipment_id')->nullable()->index();
            $table->string('seal_number', 100);
            $table->string('location', 255)->nullable();
            $table->unsignedBigInteger('applied_by');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'work_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seal_applications');
    }
};
