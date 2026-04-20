<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, array{table: string, column: string, index: string}>
     */
    private array $uniqueExternalIds = [
        ['table' => 'payments', 'column' => 'external_id', 'index' => 'payments_external_id_unique'],
        ['table' => 'crm_messages', 'column' => 'external_id', 'index' => 'crm_messages_external_id_unique'],
        ['table' => 'whatsapp_messages', 'column' => 'external_id', 'index' => 'whatsapp_messages_external_id_unique'],
    ];

    public function up(): void
    {
        foreach ($this->uniqueExternalIds as $definition) {
            if (! Schema::hasTable($definition['table']) || ! Schema::hasColumn($definition['table'], $definition['column'])) {
                continue;
            }

            $this->dedupeExternalId($definition['table'], $definition['column']);

            Schema::table($definition['table'], function (Blueprint $table) use ($definition): void {
                if (! $this->hasIndex($definition['table'], $definition['index'])) {
                    $table->unique($definition['column'], $definition['index']);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->uniqueExternalIds) as $definition) {
            if (! Schema::hasTable($definition['table']) || ! Schema::hasColumn($definition['table'], $definition['column'])) {
                continue;
            }

            Schema::table($definition['table'], function (Blueprint $table) use ($definition): void {
                if ($this->hasIndex($definition['table'], $definition['index'])) {
                    $table->dropUnique($definition['index']);
                }
            });
        }
    }

    private function dedupeExternalId(string $table, string $column): void
    {
        $duplicates = DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->pluck($column);

        foreach ($duplicates as $value) {
            $ids = DB::table($table)
                ->where($column, $value)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            foreach (array_slice($ids, 1) as $id) {
                DB::table($table)
                    ->where('id', $id)
                    ->update([$column => substr((string) $value, 0, 180).'_dup_'.$id]);
            }
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $row = DB::selectOne(
                "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?",
                [$table, $indexName]
            );

            return $row !== null;
        }

        $schema = $connection->getDatabaseName();
        $row = DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$schema, $table, $indexName]
        );

        return $row !== null;
    }
};
