<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_disposals')) {
            return;
        }

        Schema::create('asset_disposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_record_id')->constrained('asset_records')->cascadeOnDelete();
            $table->date('disposal_date');
            $table->string('reason', 20);
            $table->decimal('disposal_value', 15, 2)->nullable();
            $table->decimal('book_value_at_disposal', 15, 2);
            $table->decimal('gain_loss', 15, 2);
            $table->foreignId('fiscal_note_id')->nullable()->constrained('fiscal_notes')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'disposal_date'], 'asset_disposals_tenant_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
    }
};
