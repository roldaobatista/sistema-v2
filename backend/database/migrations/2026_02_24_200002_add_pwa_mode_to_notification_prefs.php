<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('central_notification_prefs') && ! Schema::hasColumn('central_notification_prefs', 'pwa_mode')) {
            Schema::table('central_notification_prefs', function (Blueprint $table) {
                $table->string('pwa_mode', 20)->nullable();
                $table->json('notify_types')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('central_notification_prefs')) {
            Schema::table('central_notification_prefs', function (Blueprint $table) {
                if (Schema::hasColumn('central_notification_prefs', 'pwa_mode')) {
                    $table->dropColumn('pwa_mode');
                }
                if (Schema::hasColumn('central_notification_prefs', 'notify_types')) {
                    $table->dropColumn('notify_types');
                }
            });
        }
    }
};
