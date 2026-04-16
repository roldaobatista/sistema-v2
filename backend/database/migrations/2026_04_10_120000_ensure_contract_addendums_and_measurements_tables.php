<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure contract_addendums, contract_adjustments, and contract_measurements
 * tables exist. The original migration (2026_02_18_100003) had a partial
 * failure that left these tables missing even though the migration was
 * recorded as executed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_addendums')) {
            Schema::create('contract_addendums', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->unsignedBigInteger('contract_id');
                $table->string('type'); // value_change, scope_change, term_extension, cancellation
                $table->text('description');
                $table->decimal('new_value', 15, 2)->nullable();
                $table->date('new_end_date')->nullable();
                $table->date('effective_date');
                $table->string('status')->default('pending');
                $table->foreignId('created_by')->constrained('users');
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('contract_id');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('contract_adjustments')) {
            Schema::create('contract_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->unsignedBigInteger('contract_id');
                $table->decimal('old_value', 15, 2);
                $table->decimal('new_value', 15, 2);
                $table->decimal('index_rate', 8, 4);
                $table->date('effective_date');
                $table->foreignId('applied_by')->constrained('users');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('contract_id');
            });
        }

        if (! Schema::hasTable('contract_measurements')) {
            Schema::create('contract_measurements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->unsignedBigInteger('contract_id');
                $table->string('period');
                $table->json('items');
                $table->decimal('total_accepted', 15, 2)->default(0);
                $table->decimal('total_rejected', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->string('status')->default('pending_approval');
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('contract_id');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_measurements');
        Schema::dropIfExists('contract_adjustments');
        Schema::dropIfExists('contract_addendums');
    }
};
