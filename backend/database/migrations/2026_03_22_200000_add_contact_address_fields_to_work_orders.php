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

        Schema::table('work_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('work_orders', 'address')) {
                $table->string('address', 255)->nullable();
            }
            if (! Schema::hasColumn('work_orders', 'city')) {
                $table->string('city', 100)->nullable();
            }
            if (! Schema::hasColumn('work_orders', 'state')) {
                $table->string('state', 2)->nullable();
            }
            if (! Schema::hasColumn('work_orders', 'zip_code')) {
                $table->string('zip_code', 10)->nullable();
            }
            if (! Schema::hasColumn('work_orders', 'contact_phone')) {
                $table->string('contact_phone', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_orders')) {
            return;
        }

        Schema::table('work_orders', function (Blueprint $table) {
            $cols = ['address', 'city', 'state', 'zip_code', 'contact_phone'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('work_orders', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
