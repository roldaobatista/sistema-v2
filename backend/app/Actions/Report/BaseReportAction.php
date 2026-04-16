<?php

namespace App\Actions\Report;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

abstract class BaseReportAction
{
    /**
     * @param  literal-string  $column
     * @return literal-string
     */
    protected function yearMonthExpression(string $column): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return "strftime('%Y-%m', {$column})";
        }

        return "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /**
     * @param  literal-string  $startColumn
     * @param  literal-string  $endColumn
     * @return literal-string
     */
    protected function avgHoursExpression(string $startColumn, string $endColumn): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return "AVG((julianday({$endColumn}) - julianday({$startColumn})) * 24)";
        }

        return "AVG(TIMESTAMPDIFF(HOUR, {$startColumn}, {$endColumn}))";
    }

    /**
     * @param  array<int|string, mixed>  $filters
     */
    protected function validatedDate(array $filters, string $key, string $default): string
    {
        $value = $filters[$key] ?? $default;

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * @param  array<int|string, mixed>  $filters
     */
    protected function osNumberFilter(array $filters): ?string
    {
        $osNumber = trim((string) ($filters['os_number'] ?? ''));
        if ($osNumber === '') {
            return null;
        }

        return str_replace(['%', '_'], ['\%', '\_'], $osNumber);
    }

    /**
     * @param  array<int|string, mixed>  $filters
     */
    protected function branchId(array $filters): ?int
    {
        return isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
    }

    /**
     * @param  mixed  $query
     * @return mixed
     */
    protected function applyBranchFilter($query, ?int $branchId, string $column = 'branch_id')
    {
        if ($branchId) {
            $query->where($column, $branchId);
        }

        return $query;
    }

    /**
     * @param  mixed  $query
     * @return mixed
     */
    protected function applyWorkOrderFilter($query, string $relation, ?string $osNumber)
    {
        if (! $osNumber) {
            return $query;
        }

        return $query->whereHas($relation, function ($wo) use ($osNumber) {
            $wo->where(function ($q) use ($osNumber) {
                $q->where('os_number', 'like', "%{$osNumber}%")
                    ->orWhere('number', 'like', "%{$osNumber}%");
            });
        });
    }

    /**
     * @param  mixed  $query
     * @return mixed
     */
    protected function applyPayableIdentifierFilter($query, ?string $osNumber)
    {
        if (! $osNumber) {
            return $query;
        }

        return $query->where(function ($q) use ($osNumber) {
            $q->where('description', 'like', "%{$osNumber}%")
                ->orWhere('notes', 'like', "%{$osNumber}%");
        });
    }

    /**
     * @param  mixed  $query
     * @return mixed
     */
    protected function applyPayableAliasIdentifierFilter($query, ?string $osNumber, string $alias)
    {
        if (! $osNumber) {
            return $query;
        }

        return $query->where(function ($q) use ($osNumber, $alias) {
            $q->where("{$alias}.description", 'like', "%{$osNumber}%")
                ->orWhere("{$alias}.notes", 'like', "%{$osNumber}%");
        });
    }

    /**
     * @param  mixed  $query
     * @return mixed
     */
    protected function applyReceivablePaymentReportFilters($query, ?string $osNumber, ?int $branchId, string $receivableAlias = 'ar_report', string $workOrderAlias = 'wo_report')
    {
        if ($osNumber) {
            $query->where(function ($q) use ($osNumber, $workOrderAlias) {
                $q->where("{$workOrderAlias}.os_number", 'like', "%{$osNumber}%")
                    ->orWhere("{$workOrderAlias}.number", 'like', "%{$osNumber}%");
            });
        }

        if ($branchId) {
            $query->where(function ($q) use ($branchId, $receivableAlias, $workOrderAlias) {
                $q->where("{$workOrderAlias}.branch_id", $branchId)
                    ->orWhereNull("{$receivableAlias}.work_order_id");
            });
        }

        return $query;
    }
}
