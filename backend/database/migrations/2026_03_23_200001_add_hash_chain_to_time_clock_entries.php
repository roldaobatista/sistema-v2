<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('time_clock_entries')) {
            return;
        }

        Schema::table('time_clock_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('time_clock_entries', 'record_hash')) {
                $table->string('record_hash', 64)->default('')->after('work_order_id');
            }
            if (! Schema::hasColumn('time_clock_entries', 'previous_hash')) {
                $table->string('previous_hash', 64)->nullable()->after('record_hash');
            }
            if (! Schema::hasColumn('time_clock_entries', 'hash_payload')) {
                $table->text('hash_payload')->nullable()->after('previous_hash');
            }
            if (! Schema::hasColumn('time_clock_entries', 'nsr')) {
                $table->unsignedBigInteger('nsr')->nullable()->after('hash_payload');
            }
        });

        // Add unique index on (tenant_id, nsr) — must be outside the column-add closure
        // to avoid issues with SQLite and some MySQL versions
        if (Schema::hasColumn('time_clock_entries', 'nsr') && Schema::hasColumn('time_clock_entries', 'tenant_id')) {
            try {
                Schema::table('time_clock_entries', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'nsr'], 'time_clock_entries_tenant_nsr_unique');
                });
            } catch (Exception $e) {
                // Index may already exist
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('time_clock_entries')) {
            return;
        }

        try {
            Schema::table('time_clock_entries', function (Blueprint $table) {
                $table->dropUnique('time_clock_entries_tenant_nsr_unique');
            });
        } catch (Exception $e) {
            // Index may not exist
        }

        $columns = ['record_hash', 'previous_hash', 'hash_payload', 'nsr'];
        foreach ($columns as $col) {
            if (Schema::hasColumn('time_clock_entries', $col)) {
                Schema::table('time_clock_entries', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
