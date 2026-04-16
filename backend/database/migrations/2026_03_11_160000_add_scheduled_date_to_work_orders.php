<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_orders') || Schema::hasColumn('work_orders', 'scheduled_date')) {
            return;
        }

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dateTime('scheduled_date')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_orders') || ! Schema::hasColumn('work_orders', 'scheduled_date')) {
            return;
        }

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn('scheduled_date');
        });
    }
};
