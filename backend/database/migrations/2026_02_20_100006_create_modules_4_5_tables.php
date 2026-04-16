<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 4.31 - Customer Health Scores (Churn Predictor)
        if (! Schema::hasTable('customer_health_scores')) {
            Schema::create('customer_health_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->integer('health_index')->default(100);
                $table->string('risk_level')->default('low'); // high, medium, low
                $table->json('factors')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'customer_id']);
            });
        }

        // 4.33 - Magic Token for Quote approval
        if (Schema::hasTable('quotes')) {
            if (! Schema::hasColumn('quotes', 'magic_token')) {
                Schema::table('quotes', function (Blueprint $table) {
                    $table->string('magic_token', 64)->nullable()->unique();
                    $table->string('client_ip_approval')->nullable();
                    $table->timestamp('term_accepted_at')->nullable();
                });
            }
        }

        // 5.40 - Fleet Telemetry (IoT OBD2)
        if (! Schema::hasTable('fleet_telemetry')) {
            Schema::create('fleet_telemetry', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
                $table->integer('odometer')->nullable();
                $table->string('dtc_fault_codes')->nullable();
                $table->decimal('engine_temperature', 5, 1)->nullable();
                $table->decimal('fuel_level_pct', 5, 1)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamps();
            });
        }

        // 5.41 - Audit Blockchain Hashes
        if (! Schema::hasTable('audit_blockchain_hashes')) {
            Schema::create('audit_blockchain_hashes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('table_name');
                $table->unsignedBigInteger('record_id');
                $table->string('sha256_hash', 64);
                $table->string('previous_hash', 64)->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
                $table->index(['table_name', 'record_id']);
            });
        }

        // 5.44 - CRM Email Threads (IMAP)
        if (! Schema::hasTable('crm_email_threads')) {
            Schema::create('crm_email_threads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('message_id_hash', 64)->nullable()->unique();
                $table->string('subject');
                $table->text('body_text')->nullable();
                $table->string('from_email')->nullable();
                $table->timestamp('date')->nullable();
                $table->string('direction')->default('inbound'); // inbound, outbound
                $table->timestamps();
            });
        }

        // 5.45 - System Revisions (Temporal Rollback)
        if (! Schema::hasTable('system_revisions')) {
            Schema::create('system_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->json('before_payload')->nullable();
                $table->json('after_payload')->nullable();
                $table->string('action')->default('update'); // create, update, delete
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
                $table->index(['model_type', 'model_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_revisions');
        Schema::dropIfExists('crm_email_threads');
        Schema::dropIfExists('audit_blockchain_hashes');
        Schema::dropIfExists('fleet_telemetry');
        Schema::dropIfExists('customer_health_scores');

        if (Schema::hasTable('quotes')) {
            Schema::table('quotes', function (Blueprint $table) {
                if (Schema::hasColumn('quotes', 'magic_token')) {
                    $table->dropColumn(['magic_token', 'client_ip_approval', 'term_accepted_at']);
                }
            });
        }
    }
};
