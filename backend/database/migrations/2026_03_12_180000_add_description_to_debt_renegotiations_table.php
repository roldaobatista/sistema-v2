<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('debt_renegotiations') || Schema::hasColumn('debt_renegotiations', 'description')) {
            return;
        }

        Schema::table('debt_renegotiations', function (Blueprint $table) {
            $table->string('description')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('debt_renegotiations') || ! Schema::hasColumn('debt_renegotiations', 'description')) {
            return;
        }

        Schema::table('debt_renegotiations', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
