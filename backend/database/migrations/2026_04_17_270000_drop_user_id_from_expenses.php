<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expenses') || ! Schema::hasColumn('expenses', 'user_id')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('expenses') || Schema::hasColumn('expenses', 'user_id')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('created_by');
        });
    }
};
