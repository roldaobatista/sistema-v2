<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['accounts_payable', 'accounts_receivable'];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'updated_by')) {
                    $t->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($table, 'deleted_by')) {
                    $t->foreignId('deleted_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        $tables = ['accounts_payable', 'accounts_receivable'];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'deleted_by')) {
                    try {
                        $t->dropForeign(['deleted_by']);
                    } catch (Throwable) {
                        // FK pode não existir em SQLite
                    }
                    $t->dropColumn('deleted_by');
                }
                if (Schema::hasColumn($table, 'updated_by')) {
                    try {
                        $t->dropForeign(['updated_by']);
                    } catch (Throwable) {
                        // FK pode não existir em SQLite
                    }
                    $t->dropColumn('updated_by');
                }
            });
        }
    }
};
