<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('travel_expense_reports')) {
            return;
        }

        if (! Schema::hasColumn('travel_expense_reports', 'user_id')) {
            return;
        }

        Schema::table('travel_expense_reports', function (Blueprint $table) {
            $table->renameColumn('user_id', 'created_by');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('travel_expense_reports')) {
            return;
        }

        if (! Schema::hasColumn('travel_expense_reports', 'created_by')) {
            return;
        }

        Schema::table('travel_expense_reports', function (Blueprint $table) {
            $table->renameColumn('created_by', 'user_id');
        });
    }
};
