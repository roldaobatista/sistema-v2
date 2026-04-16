<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'external_id')) {
                $table->string('external_id')->nullable()->after('notes')
                    ->index('idx_payments_external_id');
            }
            if (! Schema::hasColumn('payments', 'status')) {
                $table->string('status')->default('pending')->after('external_id');
            }
            if (! Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('payments', 'gateway_response')) {
                $table->json('gateway_response')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('payments', 'gateway_provider')) {
                $table->string('gateway_provider')->nullable()->after('gateway_response');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $columns = ['external_id', 'status', 'paid_at', 'gateway_response', 'gateway_provider'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
