<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journey_blocks') && ! Schema::hasColumn('journey_blocks', 'journey_entry_id')) {
            Schema::table('journey_blocks', function (Blueprint $table) {
                $table->foreignId('journey_entry_id')->nullable()->constrained('journey_entries');
            });
        }

        if (Schema::hasTable('journey_approvals') && ! Schema::hasColumn('journey_approvals', 'journey_entry_id')) {
            Schema::table('journey_approvals', function (Blueprint $table) {
                $table->foreignId('journey_entry_id')->nullable()->constrained('journey_entries');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('journey_blocks', 'journey_entry_id')) {
            Schema::table('journey_blocks', function (Blueprint $table) {
                $table->dropColumn('journey_entry_id');
            });
        }

        if (Schema::hasColumn('journey_approvals', 'journey_entry_id')) {
            Schema::table('journey_approvals', function (Blueprint $table) {
                $table->dropColumn('journey_entry_id');
            });
        }
    }
};
