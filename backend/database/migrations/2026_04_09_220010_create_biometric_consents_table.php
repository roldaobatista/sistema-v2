<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('biometric_consents')) {
            return;
        }

        Schema::create('biometric_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('data_type');
            $table->string('legal_basis');
            $table->text('purpose');
            $table->date('consented_at');
            $table->date('expires_at')->nullable();
            $table->date('revoked_at')->nullable();
            $table->string('alternative_method')->nullable();
            $table->integer('retention_days')->default(365);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'data_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_consents');
    }
};
