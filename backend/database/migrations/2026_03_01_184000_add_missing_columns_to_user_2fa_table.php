<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_2fa')) {
            Schema::table('user_2fa', function (Blueprint $t) {
                if (! Schema::hasColumn('user_2fa', 'method')) {
                    $t->string('method', 20)->default('email')->after('secret');
                }
                if (! Schema::hasColumn('user_2fa', 'backup_codes')) {
                    $t->text('backup_codes')->nullable()->after('verified_at');
                }
                if (! Schema::hasColumn('user_2fa', 'tenant_id')) {
                    $t->unsignedBigInteger('tenant_id')->nullable()->after('user_id');
                    $t->index('tenant_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_2fa')) {
            Schema::table('user_2fa', function (Blueprint $t) {
                $columns = [];
                if (Schema::hasColumn('user_2fa', 'method')) {
                    $columns[] = 'method';
                }
                if (Schema::hasColumn('user_2fa', 'backup_codes')) {
                    $columns[] = 'backup_codes';
                }
                if (Schema::hasColumn('user_2fa', 'tenant_id')) {
                    $columns[] = 'tenant_id';
                }
                if (! empty($columns)) {
                    $t->dropColumn($columns);
                }
            });
        }
    }
};
