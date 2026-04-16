<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Align whatsapp_messages table with WhatsappMessageLog model.
 * The original migration only created basic columns; the model expects
 * direction, phone_from, phone_to, message_type, template_name/params,
 * external_id, error_message, related morph, delivery timestamps, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_messages', 'direction')) {
                $table->string('direction', 10)->default('outbound')->after('tenant_id');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'phone_to')) {
                $table->string('phone_to', 50)->nullable()->after('direction');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'phone_from')) {
                $table->string('phone_from', 50)->nullable()->after('phone_to');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'message_type')) {
                $table->string('message_type', 30)->default('text')->after('message');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'template_name')) {
                $table->string('template_name')->nullable()->after('message_type');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'template_params')) {
                $table->json('template_params')->nullable()->after('template_name');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'external_id')) {
                $table->string('external_id')->nullable()->after('status');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'error_message')) {
                $table->text('error_message')->nullable()->after('external_id');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'related_type')) {
                $table->string('related_type')->nullable()->after('error_message');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'related_id')) {
                $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('related_id');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('sent_at');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('delivered_at');
            }
            if (! Schema::hasColumn('whatsapp_messages', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $cols = [
                'direction', 'phone_to', 'phone_from', 'message_type',
                'template_name', 'template_params', 'external_id', 'error_message',
                'related_type', 'related_id', 'sent_at', 'delivered_at', 'read_at', 'updated_at',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('whatsapp_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
