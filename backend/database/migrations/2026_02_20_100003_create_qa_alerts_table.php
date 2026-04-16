<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qa_alerts')) {
            Schema::create('qa_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('calibration_id')->constrained('equipment_calibrations')->cascadeOnDelete();
                $table->decimal('similarity_score', 5, 2)->default(0);
                $table->string('reason');
                $table->string('status')->default('pending'); // pending, reviewed, dismissed
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_alerts');
    }
};
