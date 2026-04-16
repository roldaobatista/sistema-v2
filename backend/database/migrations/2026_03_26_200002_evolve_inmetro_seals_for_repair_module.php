<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inmetro_seals', function (Blueprint $table) {
            if (! Schema::hasColumn('inmetro_seals', 'batch_id')) {
                $table->foreignId('batch_id')->nullable()->after('tenant_id')->constrained('repair_seal_batches')->nullOnDelete();
            }

            if (! Schema::hasColumn('inmetro_seals', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            }

            if (! Schema::hasColumn('inmetro_seals', 'psei_status')) {
                $table->string('psei_status', 20)->default('not_applicable')->after('notes')
                    ->comment('not_applicable, pending, submitted, confirmed, failed');
            }

            if (! Schema::hasColumn('inmetro_seals', 'psei_submitted_at')) {
                $table->timestamp('psei_submitted_at')->nullable()->after('psei_status');
            }

            if (! Schema::hasColumn('inmetro_seals', 'psei_protocol')) {
                $table->string('psei_protocol', 50)->nullable()->after('psei_submitted_at');
            }

            if (! Schema::hasColumn('inmetro_seals', 'deadline_at')) {
                $table->timestamp('deadline_at')->nullable()->after('psei_protocol');
            }

            if (! Schema::hasColumn('inmetro_seals', 'deadline_status')) {
                $table->string('deadline_status', 15)->default('ok')->after('deadline_at')
                    ->comment('ok, warning, critical, overdue, resolved');
            }

            if (! Schema::hasColumn('inmetro_seals', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('deadline_status');
            }

            if (! Schema::hasColumn('inmetro_seals', 'returned_reason')) {
                $table->string('returned_reason')->nullable()->after('returned_at');
            }
        });

        // Expand status enum — use string instead of enum for flexibility
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inmetro_seals MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'available'");
        }

        Schema::table('inmetro_seals', function (Blueprint $table) {
            $table->index(['tenant_id', 'psei_status'], 'idx_seals_psei_status');
            $table->index(['tenant_id', 'deadline_status'], 'idx_seals_deadline_status');
            $table->index(['tenant_id', 'batch_id'], 'idx_seals_batch');
        });
    }

    public function down(): void
    {
        Schema::table('inmetro_seals', function (Blueprint $table) {
            $table->dropIndex('idx_seals_psei_status');
            $table->dropIndex('idx_seals_deadline_status');
            $table->dropIndex('idx_seals_batch');
        });

        $columns = [
            'batch_id', 'assigned_at', 'psei_status', 'psei_submitted_at',
            'psei_protocol', 'deadline_at', 'deadline_status',
            'returned_at', 'returned_reason',
        ];

        Schema::table('inmetro_seals', function (Blueprint $table) use ($columns) {
            foreach ($columns as $col) {
                if (Schema::hasColumn('inmetro_seals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
