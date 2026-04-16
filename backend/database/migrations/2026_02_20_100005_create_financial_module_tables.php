<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 3.20 Adiantamento via PIX Emergencial
        if (! Schema::hasTable('tech_cash_advances')) {
            Schema::create('tech_cash_advances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tech_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('amount', 10, 2);
                $table->string('pix_txid')->nullable();
                $table->string('pix_key')->nullable();
                $table->string('status')->default('pending'); // pending, approved, paid, rejected
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('reason')->nullable();
                $table->timestamps();
            });
        }

        // 3.21 Cartão Corporativo Virtual
        if (! Schema::hasTable('virtual_cards')) {
            Schema::create('virtual_cards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('card_id_api')->nullable()->comment('ID na API Caju/similar');
                $table->foreignId('os_id')->nullable()->constrained('work_orders')->nullOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('limit_amount', 10, 2)->default(0);
                $table->decimal('spent_amount', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // 4.30 CRM External Leads
        if (! Schema::hasTable('crm_external_leads')) {
            Schema::create('crm_external_leads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('tax_id', 18)->nullable()->comment('CNPJ');
                $table->string('company_name');
                $table->string('rival_company_name')->nullable();
                $table->date('next_calibration_due')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('source')->default('inmetro_crawler');
                $table->string('status')->default('new'); // new, contacted, converted, discarded
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_external_leads');
        Schema::dropIfExists('virtual_cards');
        Schema::dropIfExists('tech_cash_advances');
    }
};
