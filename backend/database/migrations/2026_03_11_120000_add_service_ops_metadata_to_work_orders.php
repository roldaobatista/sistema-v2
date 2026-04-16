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

        $needsDeliveryForecast = ! Schema::hasColumn('work_orders', 'delivery_forecast');
        $needsTags = ! Schema::hasColumn('work_orders', 'tags');

        if (! $needsDeliveryForecast && ! $needsTags) {
            return;
        }

        Schema::table('work_orders', function (Blueprint $table) use ($needsDeliveryForecast, $needsTags): void {
            if ($needsDeliveryForecast) {
                $table->date('delivery_forecast')->nullable();
            }

            if ($needsTags) {
                $table->json('tags')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_orders')) {
            return;
        }

        $dropDeliveryForecast = Schema::hasColumn('work_orders', 'delivery_forecast');
        $dropTags = Schema::hasColumn('work_orders', 'tags');

        if (! $dropDeliveryForecast && ! $dropTags) {
            return;
        }

        Schema::table('work_orders', function (Blueprint $table) use ($dropDeliveryForecast, $dropTags): void {
            if ($dropDeliveryForecast) {
                $table->dropColumn('delivery_forecast');
            }

            if ($dropTags) {
                $table->dropColumn('tags');
            }
        });
    }
};
