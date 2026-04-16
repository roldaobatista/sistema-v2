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
        if (Schema::hasTable('user_competencies')) {
            return;
        }

        Schema::create('user_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipments')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method_name')->nullable();
            $table->string('category')->nullable();
            $table->enum('status', ['active', 'expired', 'suspended', 'revoked'])->default('active');
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_competencies');
    }
};
