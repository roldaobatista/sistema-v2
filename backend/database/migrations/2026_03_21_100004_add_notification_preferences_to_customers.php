<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'notification_preferences')) {
                $table->json('notification_preferences')->nullable()->after('phone')
                    ->comment('Canais preferidos: ["email","whatsapp","sms"]');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
