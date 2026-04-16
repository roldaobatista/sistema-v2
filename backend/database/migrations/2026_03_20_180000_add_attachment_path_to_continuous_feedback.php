<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('continuous_feedback')) {
            return;
        }

        if (! Schema::hasColumn('continuous_feedback', 'attachment_path')) {
            Schema::table('continuous_feedback', function (Blueprint $table) {
                $table->string('attachment_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('continuous_feedback') || ! Schema::hasColumn('continuous_feedback', 'attachment_path')) {
            return;
        }

        Schema::table('continuous_feedback', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });
    }
};
