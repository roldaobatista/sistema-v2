<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'google_calendar_token')) {
                $table->text('google_calendar_token')->nullable()->after('denied_permissions');
            }
            if (! Schema::hasColumn('users', 'google_calendar_refresh_token')) {
                $table->text('google_calendar_refresh_token')->nullable()->after('google_calendar_token');
            }
            if (! Schema::hasColumn('users', 'google_calendar_email')) {
                $table->string('google_calendar_email', 255)->nullable()->after('google_calendar_refresh_token');
            }
            if (! Schema::hasColumn('users', 'google_calendar_synced_at')) {
                $table->timestamp('google_calendar_synced_at')->nullable()->after('google_calendar_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = ['google_calendar_token', 'google_calendar_refresh_token', 'google_calendar_email', 'google_calendar_synced_at'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
