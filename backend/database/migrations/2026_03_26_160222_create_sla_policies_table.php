<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sla_policies')) {
            Schema::create('sla_policies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name');
                $table->string('priority');
                $table->integer('response_time_minutes');
                $table->integer('resolution_time_minutes');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
