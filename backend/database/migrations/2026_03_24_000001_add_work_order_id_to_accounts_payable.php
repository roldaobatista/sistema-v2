<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_payable', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts_payable', 'work_order_id')) {
                $table->unsignedBigInteger('work_order_id')->nullable()->index();
                $table->foreign('work_order_id')
                    ->references('id')
                    ->on('work_orders')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts_payable', function (Blueprint $table) {
            if (Schema::hasColumn('accounts_payable', 'work_order_id')) {
                $table->dropForeign(['work_order_id']);
                $table->dropColumn('work_order_id');
            }
        });
    }
};
