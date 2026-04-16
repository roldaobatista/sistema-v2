<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove coluna public_qr_hash de equipments (sem uso no código).
 * qr_code e qr_token continuam em uso; public_qr_hash era redundante.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('equipments') && Schema::hasColumn('equipments', 'public_qr_hash')) {
            Schema::table('equipments', function (Blueprint $table) {
                $table->dropUnique(['public_qr_hash']);
                $table->dropColumn('public_qr_hash');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('equipments') && ! Schema::hasColumn('equipments', 'public_qr_hash')) {
            Schema::table('equipments', function (Blueprint $table) {
                $table->string('public_qr_hash')->nullable()->unique();
            });
        }
    }
};
