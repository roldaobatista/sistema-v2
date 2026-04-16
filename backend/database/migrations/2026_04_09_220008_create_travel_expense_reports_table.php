<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('travel_expense_reports')) {
            return;
        }

        Schema::create('travel_expense_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('travel_request_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->decimal('total_expenses', 10, 2)->default(0);
            $table->decimal('total_advances', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['tenant_id', 'travel_request_id']);
        });

        if (Schema::hasTable('travel_expense_items')) {
            return;
        }

        Schema::create('travel_expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_expense_report_id')->constrained();
            $table->string('type');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->string('receipt_path')->nullable();
            $table->boolean('is_within_policy')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_expense_items');
        Schema::dropIfExists('travel_expense_reports');
    }
};
