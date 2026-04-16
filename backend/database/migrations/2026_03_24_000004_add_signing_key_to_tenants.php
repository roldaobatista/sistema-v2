<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants') || Schema::hasColumn('tenants', 'signing_key')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('signing_key', 64)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'signing_key')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('signing_key');
        });
    }
};
