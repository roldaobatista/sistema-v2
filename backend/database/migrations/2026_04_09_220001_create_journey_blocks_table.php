<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journey_blocks')) {
            return;
        }

        Schema::create('journey_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('journey_day_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('classification');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->foreignId('work_order_id')->nullable()->constrained();
            $table->foreignId('time_clock_entry_id')->nullable()->constrained();
            $table->foreignId('fleet_trip_id')->nullable()->constrained();
            $table->foreignId('schedule_id')->nullable()->constrained();
            $table->json('metadata')->nullable();
            $table->string('source');
            $table->boolean('is_auto_classified')->default(true);
            $table->boolean('is_manually_adjusted')->default(false);
            $table->foreignId('adjusted_by')->nullable()->constrained('users');
            $table->text('adjustment_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'user_id', 'started_at']);
            $table->index(['journey_day_id', 'classification']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_blocks');
    }
};
