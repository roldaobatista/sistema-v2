<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\AnalyticsDataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RefreshAnalyticsDatasetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_refreshes_cache_for_active_dataset(): void
    {
        Cache::flush();

        $dataset = AnalyticsDataset::factory()->create([
            'refresh_strategy' => 'hourly',
            'query_definition' => [
                'source' => 'work_orders',
                'columns' => ['id', 'status'],
            ],
        ]);

        $this->artisan('analytics:refresh-datasets')
            ->assertExitCode(0);

        $this->assertNotNull(Cache::get("analytics:dataset:{$dataset->tenant_id}:{$dataset->id}:results"));
    }
}
