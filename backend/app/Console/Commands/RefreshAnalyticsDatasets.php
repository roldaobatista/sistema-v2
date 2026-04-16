<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AnalyticsDataset;
use App\Services\Analytics\AnalyticsDatasetService;
use Illuminate\Console\Command;

class RefreshAnalyticsDatasets extends Command
{
    protected $signature = 'analytics:refresh-datasets';

    protected $description = 'Atualiza o cache de datasets analiticos ativos';

    public function handle(AnalyticsDatasetService $service): int
    {
        $datasets = AnalyticsDataset::query()
            ->where('is_active', true)
            ->whereIn('refresh_strategy', ['hourly', 'daily', 'weekly'])
            ->get();

        foreach ($datasets as $dataset) {
            $service->refreshCache($dataset);
        }

        $this->info("Datasets atualizados: {$datasets->count()}");

        return self::SUCCESS;
    }
}
