<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esocial_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('event_type', 10); // S-1200, S-1210, S-2200, S-2205, S-2206, S-2210, S-2220, S-2230, S-2240, S-2299
            $table->string('related_type', 100)->nullable(); // polymorphic
            $table->unsignedBigInteger('related_id')->nullable();
            $table->longText('xml_content')->nullable();
            $table->string('protocol_number', 50)->nullable();
            $table->string('receipt_number', 50)->nullable();
            $table->string('status', 20)->default('pending'); // pending, generating, sent, accepted, rejected, cancelled
            $table->longText('response_xml')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('response_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('batch_id', 50)->nullable();
            $table->string('environment', 10)->default('production'); // production, restricted
            $table->string('version', 10)->default('S-1.2');
            $table->timestamps();

            $table->index(['tenant_id', 'event_type', 'status']);
            $table->index(['tenant_id', 'related_type', 'related_id']);
            $table->index('batch_id');
        });

        Schema::create('esocial_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('certificate_path', 255);
            $table->text('certificate_password_encrypted'); // encrypted with app key
            $table->string('serial_number', 100)->nullable();
            $table->string('issuer', 255)->nullable();
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esocial_certificates');
        Schema::dropIfExists('esocial_events');
    }
};
