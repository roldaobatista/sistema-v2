<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix critical FK constraints missing onDelete behavior.
 *
 * Priority:
 * 1. work_order_templates.tenant_id — without cascadeOnDelete, tenant deletion is blocked
 * 2. created_by/approved_by columns — should nullOnDelete (user deleted, record survives)
 * 3. user_id on commission/financial tables — should nullOnDelete or restrictOnDelete
 */
return new class extends Migration
{
    /**
     * @return object{COLUMN_TYPE:string,IS_NULLABLE:string}|null
     */
    private function getColumnMetadata(string $table, string $column): ?object
    {
        if (DB::getDriverName() !== 'mysql') {
            return null;
        }

        $database = DB::getDatabaseName();

        /** @var array<int, object{COLUMN_TYPE:string,IS_NULLABLE:string}> $rows */
        $rows = DB::select(
            <<<'SQL'
                SELECT COLUMN_TYPE, IS_NULLABLE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                LIMIT 1
            SQL,
            [$database, $table, $column],
        );

        return $rows[0] ?? null;
    }

    /**
     * Resolve real FK names from MySQL metadata instead of issuing blind drops.
     *
     * @return list<string>
     */
    private function getForeignKeyNames(string $table, string $column): array
    {
        if (DB::getDriverName() !== 'mysql') {
            return [];
        }

        $database = DB::getDatabaseName();

        /** @var list<object{CONSTRAINT_NAME:string}> $rows */
        $rows = DB::select(
            <<<'SQL'
                SELECT DISTINCT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            SQL,
            [$database, $table, $column],
        );

        return array_values(array_unique(array_map(
            static fn (object $row): string => (string) $row->CONSTRAINT_NAME,
            $rows,
        )));
    }

    /**
     * Helper: safely drop and re-add a FK with the correct onDelete behavior.
     */
    private function rebuildFk(
        string $table,
        string $column,
        string $referencesTable,
        string $referencesColumn,
        string $onDelete,
        ?string $fkName = null,
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $possibleNames = array_values(array_unique(array_filter([
            $fkName,
            $table.'_'.$column.'_foreign',
            ...$this->getForeignKeyNames($table, $column),
        ])));

        if (DB::getDriverName() === 'mysql') {
            foreach ($possibleNames as $name) {
                try {
                    DB::statement(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $name));
                } catch (Throwable) {
                }
            }

            if ($onDelete === 'set null') {
                $metadata = $this->getColumnMetadata($table, $column);

                if ($metadata !== null && strtoupper($metadata->IS_NULLABLE) !== 'YES') {
                    DB::statement(sprintf(
                        'ALTER TABLE `%s` MODIFY `%s` %s NULL',
                        $table,
                        $column,
                        $metadata->COLUMN_TYPE,
                    ));
                }
            }
        } else {
            Schema::table($table, function (Blueprint $t) use ($column, $possibleNames) {
                foreach ($possibleNames as $name) {
                    try {
                        $t->dropForeign($name);
                    } catch (Throwable) {
                    }
                }

                try {
                    $t->dropForeign([$column]);
                } catch (Throwable) {
                }
            });
        }

        Schema::table($table, function (Blueprint $t) use ($column, $referencesTable, $referencesColumn, $onDelete) {
            $fk = $t->foreign($column)->references($referencesColumn)->on($referencesTable);
            match ($onDelete) {
                'cascade' => $fk->cascadeOnDelete(),
                'set null' => $fk->nullOnDelete(),
                'restrict' => $fk->restrictOnDelete(),
                default => $fk->nullOnDelete(),
            };
        });
    }

    public function up(): void
    {
        // ── CRITICAL: tenant_id FK must cascade on delete ──
        $this->rebuildFk('work_order_templates', 'tenant_id', 'tenants', 'id', 'cascade');

        // ── created_by / approved_by columns → set null on delete ──
        // (user deleted, but the record should survive with null reference)
        $createdByTables = [
            'work_orders' => 'created_by',
            'accounts_receivable' => 'created_by',
            'accounts_payable' => 'created_by',
            'expenses' => 'created_by',
            'bank_accounts' => 'created_by',
            'fund_transfers' => 'created_by',
            'technician_cash_transactions' => 'created_by',
        ];

        foreach ($createdByTables as $table => $column) {
            $this->rebuildFk($table, $column, 'users', 'id', 'set null');
        }

        // expenses.approved_by
        $this->rebuildFk('expenses', 'approved_by', 'users', 'id', 'set null');

        // payments.received_by
        $this->rebuildFk('payments', 'received_by', 'users', 'id', 'set null');

        // quotes.seller_id
        $this->rebuildFk('quotes', 'seller_id', 'users', 'id', 'set null');

        // work_order_status_history.user_id
        $this->rebuildFk('work_order_status_history', 'user_id', 'users', 'id', 'set null');

        // ── user_id on commission tables → set null ──
        $commissionUserTables = [
            'commission_rules',
            'commission_events',
            'commission_settlements',
            'commission_splits',
            'commission_disputes',
            'commission_goals',
            'recurring_commissions',
        ];

        foreach ($commissionUserTables as $table) {
            $this->rebuildFk($table, 'user_id', 'users', 'id', 'set null');
        }

        // ── central_items FK columns → set null ──
        $this->rebuildFk('central_items', 'responsavel_user_id', 'users', 'id', 'set null');
        $this->rebuildFk('central_items', 'criado_por_user_id', 'users', 'id', 'set null');
        $this->rebuildFk('central_item_comments', 'user_id', 'users', 'id', 'set null');

        // ── technician FK columns → set null ──
        $this->rebuildFk('schedules', 'technician_id', 'users', 'id', 'set null');
        $this->rebuildFk('time_entries', 'technician_id', 'users', 'id', 'set null');

        // ── imports/auvo_imports → cascade (import belongs to user) ──
        $this->rebuildFk('auvo_imports', 'user_id', 'users', 'id', 'set null');
    }

    public function down(): void
    {
        // Down migration would restore the FKs without onDelete (RESTRICT default).
        // This is intentionally left as a no-op since the original state was
        // already missing onDelete, and reverting to that broken state is undesirable.
    }
};
