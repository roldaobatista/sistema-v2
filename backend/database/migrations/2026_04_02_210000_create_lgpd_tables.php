<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RF-11.1: Base legal por tipo de tratamento
        Schema::create('lgpd_data_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('data_category');
            $table->string('purpose');
            $table->string('legal_basis');
            $table->text('description')->nullable();
            $table->string('data_types');
            $table->string('retention_period')->nullable();
            $table->string('retention_legal_basis')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // RF-11.5: Log de consentimento
        Schema::create('lgpd_consent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('holder_type');
            $table->unsignedBigInteger('holder_id');
            $table->string('holder_name');
            $table->string('holder_email')->nullable();
            $table->string('holder_document')->nullable();
            $table->string('purpose');
            $table->string('legal_basis');
            $table->string('status')->default('granted');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->index(['holder_type', 'holder_id']);
        });

        // RF-11.2 + RF-11.3 + RF-11.4: Solicitações do titular
        Schema::create('lgpd_data_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('protocol')->unique();
            $table->string('holder_name');
            $table->string('holder_email');
            $table->string('holder_document');
            $table->string('request_type');
            $table->string('status')->default('pending');
            $table->text('description')->nullable();
            $table->text('response_notes')->nullable();
            $table->string('response_file_path')->nullable();
            $table->date('deadline');
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // RF-11.7: DPO por tenant
        Schema::create('lgpd_dpo_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('dpo_name');
            $table->string('dpo_email');
            $table->string('dpo_phone')->nullable();
            $table->boolean('is_public')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // RF-11.8: Incidentes de segurança
        Schema::create('lgpd_security_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('protocol')->unique();
            $table->string('severity');
            $table->text('description');
            $table->text('affected_data');
            $table->integer('affected_holders_count')->default(0);
            $table->text('measures_taken')->nullable();
            $table->text('anpd_notification')->nullable();
            $table->boolean('holders_notified')->default(false);
            $table->timestamp('holders_notified_at')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('anpd_reported_at')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // RF-11.6: Log de anonimização
        Schema::create('lgpd_anonymization_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('holder_document');
            $table->json('anonymized_fields');
            $table->string('legal_basis');
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgpd_anonymization_logs');
        Schema::dropIfExists('lgpd_security_incidents');
        Schema::dropIfExists('lgpd_dpo_configs');
        Schema::dropIfExists('lgpd_data_requests');
        Schema::dropIfExists('lgpd_consent_logs');
        Schema::dropIfExists('lgpd_data_treatments');
    }
};
