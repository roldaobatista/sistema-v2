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
        Schema::create('espelho_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->integer('year');
            $table->integer('month');
            $table->string('confirmation_hash', 64);
            $table->datetime('confirmed_at');
            $table->enum('confirmation_method', ['password', 'pin', 'biometric']);
            $table->string('ip_address', 45)->nullable();
            $table->json('device_info')->nullable();
            $table->json('espelho_snapshot');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('espelho_confirmations');
    }
};
