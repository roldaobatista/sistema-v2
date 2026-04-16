<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make legacy NOT NULL columns on whatsapp_messages nullable.
 * The table was modernized with direction/phone_to/message_type/etc.
 * but the legacy `phone` and `template` columns remained NOT NULL,
 * causing constraint violations for new inserts from WhatsappMessageLog.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_messages')) {
            return;
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_messages', 'phone')) {
                $table->string('phone', 255)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // No-op: we don't want to restore NOT NULL constraint that blocks modern inserts.
    }
};
