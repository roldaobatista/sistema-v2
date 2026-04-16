<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('central_item_watchers')) {
            Schema::create('central_item_watchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agenda_item_id')->constrained('central_items')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id');
                $table->string('role', 20)->default('watcher');
                $table->boolean('notify_status_change')->default(true);
                $table->boolean('notify_comment')->default(true);
                $table->boolean('notify_due_date')->default(true);
                $table->boolean('notify_assignment')->default(true);
                $table->string('added_by_type', 20)->default('manual');
                $table->unsignedBigInteger('added_by_user_id')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('added_by_user_id')->references('id')->on('users')->nullOnDelete();
                $table->unique(['agenda_item_id', 'user_id'], 'ciw_item_user_unique');
            });
        }

        if (! Schema::hasTable('central_notification_prefs')) {
            Schema::create('central_notification_prefs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('tenant_id');
                $table->boolean('notify_assigned_to_me')->default(true);
                $table->boolean('notify_created_by_me')->default(true);
                $table->boolean('notify_watching')->default(true);
                $table->boolean('notify_mentioned')->default(true);
                $table->string('channel_in_app', 10)->default('on');
                $table->string('channel_email', 10)->default('off');
                $table->string('channel_push', 10)->default('on');
                $table->string('digest_frequency', 20)->nullable();
                $table->json('quiet_hours')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->unique(['user_id', 'tenant_id'], 'cnp_user_tenant');
            });
        }

        if (Schema::hasTable('central_items') && ! Schema::hasColumn('central_items', 'visibility_departments')) {
            Schema::table('central_items', function (Blueprint $table) {
                $table->json('visibility_departments')->nullable();
                $table->json('visibility_users')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('central_item_watchers');
        Schema::dropIfExists('central_notification_prefs');

        if (Schema::hasTable('central_items')) {
            Schema::table('central_items', function (Blueprint $table) {
                if (Schema::hasColumn('central_items', 'visibility_departments')) {
                    $table->dropColumn('visibility_departments');
                }
                if (Schema::hasColumn('central_items', 'visibility_users')) {
                    $table->dropColumn('visibility_users');
                }
            });
        }
    }
};
