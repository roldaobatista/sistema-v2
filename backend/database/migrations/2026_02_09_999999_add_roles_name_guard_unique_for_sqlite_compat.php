<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        if (! Schema::hasTable('roles')) {
            return;
        }

        try {
            Schema::table('roles', function (Blueprint $table) {
                $table->unique(['name', 'guard_name']);
            });
        } catch (Throwable) {
            // Unique index already exists or cannot be created in this state.
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite' || ! Schema::hasTable('roles')) {
            return;
        }

        try {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique(['name', 'guard_name']);
            });
        } catch (Throwable) {
            // Index does not exist or was already removed.
        }
    }
};
