<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('travel_requests')) {
            return;
        }

        Schema::create('travel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('status')->default('pending');
            $table->string('destination');
            $table->text('purpose');
            $table->date('departure_date');
            $table->date('return_date');
            $table->time('departure_time')->nullable();
            $table->time('return_time')->nullable();
            $table->integer('estimated_days');
            $table->decimal('daily_allowance_amount', 10, 2)->nullable();
            $table->decimal('total_advance_requested', 10, 2)->nullable();
            $table->boolean('requires_vehicle')->default(false);
            $table->foreignId('fleet_vehicle_id')->nullable()->constrained();
            $table->boolean('requires_overnight')->default(false);
            $table->integer('rest_days_after')->default(0);
            $table->boolean('overtime_authorized')->default(false);
            $table->json('work_orders')->nullable();
            $table->json('itinerary')->nullable();
            $table->json('meal_policy')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'user_id', 'status']);
            $table->index(['tenant_id', 'departure_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_requests');
    }
};
