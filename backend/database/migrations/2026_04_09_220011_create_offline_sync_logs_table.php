<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offline_sync_logs')) {
            return;
        }

        Schema::create('offline_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('uuid')->unique();
            $table->string('event_type');
            $table->string('status'); // accepted, duplicate, conflict, rejected
            $table->timestamp('local_timestamp');
            $table->timestamp('server_timestamp');
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_logs');
    }
};
