<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['accounts_payable', 'accounts_receivable'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'penalty_amount')) {
                    $table->decimal('penalty_amount', 15, 2)->default(0);
                }
                if (! Schema::hasColumn($tableName, 'interest_amount')) {
                    $table->decimal('interest_amount', 15, 2)->default(0);
                }
                if (! Schema::hasColumn($tableName, 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0);
                }
                if (! Schema::hasColumn($tableName, 'cost_center_id')) {
                    $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['accounts_payable', 'accounts_receivable'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'cost_center_id')) {
                    $table->dropForeign(['cost_center_id']);
                    $table->dropColumn('cost_center_id');
                }
                if (Schema::hasColumn($tableName, 'discount_amount')) {
                    $table->dropColumn('discount_amount');
                }
                if (Schema::hasColumn($tableName, 'interest_amount')) {
                    $table->dropColumn('interest_amount');
                }
                if (Schema::hasColumn($tableName, 'penalty_amount')) {
                    $table->dropColumn('penalty_amount');
                }
            });
        }
    }
};
