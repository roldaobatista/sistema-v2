<?php

namespace App\Jobs;

use App\Events\TvKpisUpdated;
use App\Services\TvDashboardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CaptureTvDashboardKpis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Execute the job.
     */
    public function handle(TvDashboardService $tvDashboardService): void
    {
        try {
            $technicians = $tvDashboardService->getTechnicians($this->tenantId);
            $activeWorkOrders = $tvDashboardService->getActiveWorkOrders();
            $kpis = $tvDashboardService->getKpis($this->tenantId, $technicians, $activeWorkOrders);

            // Dispara evento broadcast configurado no reverb
            event(new TvKpisUpdated($this->tenantId, $kpis));

        } catch (\Exception $e) {
            Log::error('Falha ao processar CaptureTvDashboardKpis', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
