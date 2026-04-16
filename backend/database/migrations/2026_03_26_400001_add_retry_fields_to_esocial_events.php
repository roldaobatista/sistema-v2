<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esocial_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('retry_count')->default(0)->after('version');
            $table->unsignedSmallInteger('max_retries')->default(3)->after('retry_count');
            $table->timestamp('last_retry_at')->nullable()->after('max_retries');
            $table->timestamp('next_retry_at')->nullable()->after('last_retry_at');
        });
    }

    public function down(): void
    {
        Schema::table('esocial_events', function (Blueprint $table) {
            $table->dropColumn(['retry_count', 'max_retries', 'last_retry_at', 'next_retry_at']);
        });
    }
};
