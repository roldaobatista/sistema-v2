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
        Schema::table('portal_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('portal_tickets', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable()->after('category');
            }
            if (! Schema::hasColumn('portal_tickets', 'paused_at')) {
                $table->timestamp('paused_at')->nullable()->after('sla_due_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portal_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('portal_tickets', 'sla_due_at')) {
                $table->dropColumn('sla_due_at');
            }
            if (Schema::hasColumn('portal_tickets', 'paused_at')) {
                $table->dropColumn('paused_at');
            }
        });
    }
};
