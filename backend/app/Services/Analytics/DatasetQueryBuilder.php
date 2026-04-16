<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AccountReceivable;
use App\Models\AnalyticsDataset;
use App\Models\Expense;
use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class DatasetQueryBuilder
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function preview(AnalyticsDataset $dataset, int $tenantId, int $limit = 25, array $filters = []): array
    {
        return $this->buildQuery($dataset, $tenantId, $filters)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function execute(AnalyticsDataset $dataset, int $tenantId, array $filters = [], int $limit = 5000): array
    {
        return $this->buildQuery($dataset, $tenantId, $filters)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $runtimeFilters
     * @return Builder<covariant Model>
     */
    private function buildQuery(AnalyticsDataset $dataset, int $tenantId, array $runtimeFilters = []): Builder
    {
        $definition = $dataset->query_definition ?? [];
        $source = Arr::get($definition, 'source', Arr::first($dataset->source_modules));
        $columns = Arr::get($definition, 'columns', ['id', 'created_at']);
        $filters = array_merge(Arr::get($definition, 'filters', []), $runtimeFilters);
        $orderBy = Arr::get($definition, 'order_by', []);

        [$query, $allowedColumns] = $this->resolveSource((string) $source, $tenantId);

        /** @var array<int, string> $columnList */
        $columnList = is_array($columns) ? array_values(array_filter($columns, 'is_string')) : [];

        $safeColumns = collect($columnList)
            ->filter(fn ($column) => in_array($column, $allowedColumns, true))
            ->values()
            ->all();

        if ($safeColumns === []) {
            throw new InvalidArgumentException('No valid columns configured for analytics dataset.');
        }

        $query->select($safeColumns);

        foreach ($filters as $key => $value) {
            if (in_array($key, $allowedColumns, true)) {
                $query->where($key, $value);
            }
        }

        foreach ($orderBy as $order) {
            $column = Arr::get($order, 'column');
            $direction = strtolower((string) Arr::get($order, 'direction', 'asc'));

            if (in_array($column, $allowedColumns, true)) {
                $query->orderBy($column, $direction === 'desc' ? 'desc' : 'asc');
            }
        }

        return $query;
    }

    /**
     * @return array{0: Builder<covariant Model>, 1: array<int, string>}
     */
    private function resolveSource(string $source, int $tenantId): array
    {
        return match ($source) {
            'work_orders' => [
                WorkOrder::query()->where('tenant_id', $tenantId),
                ['id', 'status', 'created_at', 'completed_at', 'total', 'customer_id'],
            ],
            'finance' => [
                AccountReceivable::query()->where('tenant_id', $tenantId),
                ['id', 'status', 'created_at', 'due_date', 'amount', 'amount_paid', 'customer_id'],
            ],
            'crm' => [
                Quote::query()->where('tenant_id', $tenantId),
                ['id', 'status', 'created_at', 'total', 'customer_id'],
            ],
            'quality', 'hr' => [
                Expense::query()->where('tenant_id', $tenantId),
                ['id', 'status', 'created_at', 'amount', 'category_id'],
            ],
            default => throw new InvalidArgumentException('Unsupported analytics dataset source.'),
        };
    }
}
