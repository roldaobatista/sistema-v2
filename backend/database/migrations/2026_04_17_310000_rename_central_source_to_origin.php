<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('central_items')
            && Schema::hasColumn('central_items', 'source')
            && ! Schema::hasColumn('central_items', 'origin')) {
            Schema::table('central_items', function (Blueprint $table) {
                $table->renameColumn('source', 'origin');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('central_items')
            && Schema::hasColumn('central_items', 'origin')
            && ! Schema::hasColumn('central_items', 'source')) {
            Schema::table('central_items', function (Blueprint $table) {
                $table->renameColumn('origin', 'source');
            });
        }
    }
};
