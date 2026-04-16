<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_seal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['seal', 'seal_reparo'])->comment('seal = lacre, seal_reparo = selo inmetro');
            $table->string('batch_code', 50);
            $table->string('range_start', 30);
            $table->string('range_end', 30);
            $table->string('prefix', 10)->nullable();
            $table->string('suffix', 10)->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_available');
            $table->string('supplier')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('received_at');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'batch_code']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_seal_batches');
    }
};
