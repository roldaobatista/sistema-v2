<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('time_clock_entries') && ! Schema::hasColumn('time_clock_entries', 'archived_at')) {
            Schema::table('time_clock_entries', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->after('updated_at');
                $table->index('archived_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('time_clock_entries') && Schema::hasColumn('time_clock_entries', 'archived_at')) {
            Schema::table('time_clock_entries', function (Blueprint $table) {
                $table->dropIndex(['archived_at']);
                $table->dropColumn('archived_at');
            });
        }
    }
};
