<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('quotes', 'general_conditions')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->text('general_conditions')->nullable()->after('payment_terms_detail');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('quotes', 'general_conditions')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->dropColumn('general_conditions');
            });
        }
    }
};
