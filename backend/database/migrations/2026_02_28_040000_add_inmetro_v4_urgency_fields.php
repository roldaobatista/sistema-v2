<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inmetro_base_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('inmetro_base_configs', 'psie_username')) {
                $table->string('psie_username')->nullable();
            }
            if (! Schema::hasColumn('inmetro_base_configs', 'psie_password')) {
                $table->text('psie_password')->nullable();
            }
            if (! Schema::hasColumn('inmetro_base_configs', 'last_rejection_check_at')) {
                $table->timestamp('last_rejection_check_at')->nullable();
            }
            if (! Schema::hasColumn('inmetro_base_configs', 'notification_roles')) {
                $table->json('notification_roles')->nullable();
            }
            if (! Schema::hasColumn('inmetro_base_configs', 'whatsapp_message_template')) {
                $table->text('whatsapp_message_template')->nullable();
            }
            if (! Schema::hasColumn('inmetro_base_configs', 'email_subject_template')) {
                $table->string('email_subject_template')->nullable();
            }
            if (! Schema::hasColumn('inmetro_base_configs', 'email_body_template')) {
                $table->text('email_body_template')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inmetro_base_configs', function (Blueprint $table) {
            $table->dropColumn([
                'psie_username',
                'psie_password',
                'last_rejection_check_at',
                'notification_roles',
                'whatsapp_message_template',
                'email_subject_template',
                'email_body_template',
            ]);
        });
    }
};
