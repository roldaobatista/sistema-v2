<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\DepreciationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunMonthlyDepreciation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly ?string $referenceMonth = null,
    ) {}

    public function handle(DepreciationService $depreciationService): void
    {
        $referenceMonth = $this->referenceMonth ?? Carbon::now()->startOfMonth()->format('Y-m');

        Tenant::query()
            ->when(defined(Tenant::class.'::STATUS_ACTIVE'), fn ($query) => $query->where('status', Tenant::STATUS_ACTIVE))
            ->pluck('id')
            ->each(function (int $tenantId) use ($depreciationService, $referenceMonth): void {
                app()->instance('current_tenant_id', $tenantId);
                $depreciationService->runForAllAssets($tenantId, $referenceMonth, 'automatic_job');
            });
    }
}
