<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('overnight_stays')) {
            return;
        }

        Schema::create('overnight_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('travel_request_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('stay_date');
            $table->string('hotel_name')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['tenant_id', 'travel_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overnight_stays');
    }
};
