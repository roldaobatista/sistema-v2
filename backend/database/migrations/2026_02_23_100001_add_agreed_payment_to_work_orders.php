<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_orders')) {
            return;
        }

        if (! Schema::hasColumn('work_orders', 'agreed_payment_method')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->string('agreed_payment_method', 50)->nullable();
            });
        }

        if (! Schema::hasColumn('work_orders', 'agreed_payment_notes')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->string('agreed_payment_notes', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_orders')) {
            return;
        }

        if (Schema::hasColumn('work_orders', 'agreed_payment_method')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->dropColumn('agreed_payment_method');
            });
        }

        if (Schema::hasColumn('work_orders', 'agreed_payment_notes')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->dropColumn('agreed_payment_notes');
            });
        }
    }
};
