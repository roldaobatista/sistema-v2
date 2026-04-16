<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('whatsapp_messages', 'direction')) {
            return;
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('direction', 10)->default('outbound')->after('tenant_id');
            $table->string('phone_from', 50)->nullable()->after('customer_id');
            $table->string('phone_to', 50)->nullable()->after('phone_from');
            $table->string('message_type', 30)->default('text')->after('message');
            $table->string('template_name')->nullable()->after('message_type');
            $table->json('template_params')->nullable()->after('template_name');
            $table->string('external_id')->nullable()->after('status');
            $table->text('error_message')->nullable()->after('external_id');
            $table->string('related_type')->nullable()->after('error_message');
            $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            $table->timestamp('sent_at')->nullable()->after('related_id');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
            $table->timestamp('updated_at')->nullable()->after('created_at');

            $table->index('external_id');
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('whatsapp_messages', 'direction')) {
            return;
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropIndex(['related_type', 'related_id']);
            $table->dropColumn([
                'direction', 'phone_from', 'phone_to', 'message_type',
                'template_name', 'template_params', 'external_id', 'error_message',
                'related_type', 'related_id', 'sent_at', 'delivered_at', 'read_at',
                'updated_at',
            ]);
        });
    }
};
