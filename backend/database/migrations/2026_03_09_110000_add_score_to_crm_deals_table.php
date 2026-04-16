<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_deals', 'score')) {
            Schema::table('crm_deals', function (Blueprint $table) {
                $table->decimal('score', 5, 2)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_deals', 'score')) {
            Schema::table('crm_deals', function (Blueprint $table) {
                $table->dropColumn('score');
            });
        }
    }
};
