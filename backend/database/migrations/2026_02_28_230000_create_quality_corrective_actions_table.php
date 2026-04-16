<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('quality_audit_id')->index();
            $table->unsignedBigInteger('quality_audit_item_id')->nullable()->index();
            $table->text('description');
            $table->text('root_cause')->nullable();
            $table->text('action_taken')->nullable();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('status', 30)->default('open');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('quality_audit_id')->references('id')->on('quality_audits')->cascadeOnDelete();
            $table->foreign('quality_audit_item_id')->references('id')->on('quality_audit_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_corrective_actions');
    }
};
