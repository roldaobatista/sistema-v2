<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('analytics_datasets')) {
            return;
        }

        Schema::create('analytics_datasets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('source_modules');
            $table->json('query_definition');
            $table->string('refresh_strategy', 20)->default('manual');
            $table->unsignedInteger('cache_ttl_minutes')->default(1440);
            $table->timestamp('last_refreshed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'is_active'], 'analytics_datasets_tenant_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_datasets');
    }
};
