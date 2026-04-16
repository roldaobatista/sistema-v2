<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quotes') && ! Schema::hasColumn('quotes', 'is_installation_testing')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->boolean('is_installation_testing')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quotes', 'is_installation_testing')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->dropColumn('is_installation_testing');
            });
        }
    }
};
