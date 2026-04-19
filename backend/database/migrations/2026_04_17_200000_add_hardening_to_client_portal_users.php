<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_portal_users')) {
            return;
        }

        Schema::table('client_portal_users', function (Blueprint $table) {
            if (! Schema::hasColumn('client_portal_users', 'failed_login_attempts')) {
                $table->unsignedInteger('failed_login_attempts')->default(0)->after('is_active');
            }
            if (! Schema::hasColumn('client_portal_users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            }
            if (! Schema::hasColumn('client_portal_users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('password');
            }
            if (! Schema::hasColumn('client_portal_users', 'password_history')) {
                $table->json('password_history')->nullable()->after('password_changed_at');
            }
            if (! Schema::hasColumn('client_portal_users', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false)->after('locked_until');
            }
            if (! Schema::hasColumn('client_portal_users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            }
            if (! Schema::hasColumn('client_portal_users', 'two_factor_recovery_codes')) {
                $table->json('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            }
            if (! Schema::hasColumn('client_portal_users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_portal_users')) {
            return;
        }

        Schema::table('client_portal_users', function (Blueprint $table) {
            $cols = [
                'failed_login_attempts',
                'locked_until',
                'password_changed_at',
                'password_history',
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('client_portal_users', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
