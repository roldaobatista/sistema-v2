<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_calls', 'sla_due_at')) {
            Schema::table('service_calls', function (Blueprint $table) {
                $table->timestamp('sla_due_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('service_calls', 'sla_due_at')) {
            Schema::table('service_calls', function (Blueprint $table) {
                $table->dropColumn('sla_due_at');
            });
        }
    }
};
