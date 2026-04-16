<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action', 30); // create, update, delete
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->unsignedSmallInteger('priority')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'status']);
            $table->index(['status', 'priority']);
        });

        Schema::create('kiosk_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_identifier', 100);
            $table->string('status', 20)->default('active'); // active, idle, expired, closed
            $table->json('allowed_pages')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['device_identifier', 'status']);
        });

        Schema::create('offline_map_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->json('bounds'); // {north, south, east, west}
            $table->unsignedSmallInteger('zoom_min')->default(10);
            $table->unsignedSmallInteger('zoom_max')->default(16);
            $table->decimal('estimated_size_mb', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_map_regions');
        Schema::dropIfExists('kiosk_sessions');
        Schema::dropIfExists('sync_queue_items');
    }
};
