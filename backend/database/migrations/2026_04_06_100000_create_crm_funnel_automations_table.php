<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_funnel_automations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('pipeline_id')->nullable();
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->string('name');
            $table->string('trigger_event');
            $table->json('conditions')->nullable();
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('pipeline_id')->references('id')->on('crm_pipelines')->nullOnDelete();
            $table->foreign('stage_id')->references('id')->on('crm_pipeline_stages')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_funnel_automations');
    }
};
