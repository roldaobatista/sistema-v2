<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fuel_logs')) {
            return;
        }

        Schema::table('fuel_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('fuel_logs', 'distance_km')) {
                $table->decimal('distance_km', 12, 2)->nullable();
            }

            if (! Schema::hasColumn('fuel_logs', 'total_cost')) {
                $table->decimal('total_cost', 12, 2)->nullable();
            }

            if (! Schema::hasColumn('fuel_logs', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fuel_logs')) {
            return;
        }

        Schema::table('fuel_logs', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('fuel_logs', 'distance_km')) {
                $columns[] = 'distance_km';
            }

            if (Schema::hasColumn('fuel_logs', 'total_cost')) {
                $columns[] = 'total_cost';
            }

            if (Schema::hasColumn('fuel_logs', 'created_by')) {
                $columns[] = 'created_by';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
