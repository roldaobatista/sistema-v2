<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_seal_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seal_id')->constrained('inmetro_seals')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->string('alert_type', 20)->comment('warning_3d, critical_4d, overdue_5d, blocked, low_stock');
            $table->string('severity', 10)->comment('info, warning, critical');
            $table->text('message');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'technician_id', 'resolved_at'], 'idx_alerts_tech_resolved');
            $table->index(['tenant_id', 'alert_type', 'resolved_at'], 'idx_alerts_type_resolved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_seal_alerts');
    }
};
