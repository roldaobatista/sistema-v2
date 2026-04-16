<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsDataset;
use Illuminate\Support\Facades\Cache;

class AnalyticsDatasetService
{
    public function __construct(
        private readonly DatasetQueryBuilder $queryBuilder,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function preview(AnalyticsDataset $dataset, int $tenantId, int $limit = 25): array
    {
        return $this->queryBuilder->preview($dataset, $tenantId, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function refreshCache(AnalyticsDataset $dataset): array
    {
        $tenantId = (int) $dataset->tenant_id;
        $results = $this->queryBuilder->execute($dataset, $tenantId);

        Cache::put(
            $this->cacheKey($tenantId, (int) $dataset->id),
            $results,
            now()->addMinutes((int) $dataset->cache_ttl_minutes)
        );

        $dataset->forceFill([
            'last_refreshed_at' => now(),
        ])->save();

        return $results;
    }

    public function cacheKey(int $tenantId, int $datasetId): string
    {
        return "analytics:dataset:{$tenantId}:{$datasetId}:results";
    }
}
