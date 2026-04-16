<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('technician_certifications')) {
            return;
        }

        Schema::create('technician_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('type');
            $table->string('name');
            $table->string('number')->nullable();
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->string('issuer')->nullable();
            $table->string('document_path')->nullable();
            $table->string('status')->default('valid');
            $table->json('required_for_service_types')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'user_id', 'type']);
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_certifications');
    }
};
