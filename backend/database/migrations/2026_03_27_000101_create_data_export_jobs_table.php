<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('data_export_jobs')) {
            return;
        }

        Schema::create('data_export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analytics_dataset_id')->constrained('analytics_datasets')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('pending');
            $table->json('source_modules')->nullable();
            $table->json('filters')->nullable();
            $table->string('output_format', 10);
            $table->string('output_path')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('rows_exported')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('scheduled_cron', 100)->nullable();
            $table->timestamp('last_scheduled_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status'], 'data_export_jobs_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_export_jobs');
    }
};
