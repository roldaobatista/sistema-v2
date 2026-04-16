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
        Schema::create('tv_dashboard_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('TV Principal');
            $table->boolean('is_default')->default(false);
            $table->string('default_mode')->default('dashboard'); // dashboard, cameras, split
            $table->integer('rotation_interval')->default(60);
            $table->string('camera_grid')->default('2x2');
            $table->boolean('alert_sound')->default(true);
            $table->string('kiosk_pin')->nullable();

            // Timers & SLAs
            $table->integer('technician_offline_minutes')->default(15);
            $table->integer('unattended_call_minutes')->default(30);
            $table->integer('kpi_refresh_seconds')->default(30);
            $table->integer('alert_refresh_seconds')->default(60);
            $table->integer('cache_ttl_seconds')->default(30);

            $table->json('widgets')->nullable();

            $table->timestamps();

            // Um tenant não pode ter múltiplas configs default, mas isso é validado na Controller.
            $table->index(['tenant_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tv_dashboard_configs');
    }
};
