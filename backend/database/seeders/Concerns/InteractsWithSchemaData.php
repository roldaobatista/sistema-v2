<?php

namespace Database\Seeders\Concerns;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait InteractsWithSchemaData
{
    protected array $schemaColumnsCache = [];

    protected function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    protected function hasColumns(string $table, array $columns): bool
    {
        if (! $this->tableExists($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    protected function upsertAndGetId(string $table, array $keys, array $values = []): ?int
    {
        if (! $this->tableExists($table)) {
            return null;
        }

        $filteredKeys = $this->normalizeValues($this->filterColumns($table, $keys));
        if ($filteredKeys === []) {
            return null;
        }

        $filteredValues = $this->normalizeValues($this->filterColumns($table, $values));
        $columns = $this->getColumns($table);
        $now = now();

        if (in_array('updated_at', $columns, true)) {
            $filteredValues['updated_at'] = $now;
        }

        $query = DB::table($table);
        foreach ($filteredKeys as $column => $value) {
            $query->where($column, $value);
        }

        $existing = $query->first();
        if ($existing) {
            if ($filteredValues !== []) {
                DB::table($table)->where('id', $existing->id)->update($filteredValues);
            }

            return (int) $existing->id;
        }

        if (in_array('created_at', $columns, true)) {
            $filteredValues['created_at'] = $now;
        }

        $insert = array_merge($filteredKeys, $filteredValues);
        if (! array_key_exists('id', $insert) && in_array('id', $columns, true)) {
            return (int) DB::table($table)->insertGetId($insert);
        }

        DB::table($table)->insert($insert);

        return null;
    }

    protected function upsertRow(string $table, array $keys, array $values = []): void
    {
        $this->upsertAndGetId($table, $keys, $values);
    }

    protected function filterColumns(string $table, array $payload): array
    {
        $columns = $this->getColumns($table);
        $allowed = array_flip($columns);

        return array_intersect_key($payload, $allowed);
    }

    protected function getColumns(string $table): array
    {
        if (! isset($this->schemaColumnsCache[$table])) {
            $this->schemaColumnsCache[$table] = Schema::hasTable($table)
                ? Schema::getColumnListing($table)
                : [];
        }

        return $this->schemaColumnsCache[$table];
    }

    protected function normalizeValues(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $values[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $values;
    }
}
