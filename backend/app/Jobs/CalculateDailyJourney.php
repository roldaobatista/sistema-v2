<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\JourneyCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateDailyJourney implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function handle(JourneyCalculationService $service): void
    {
        $yesterday = now()->subDay()->toDateString();

        // Group active employees by tenant to set tenant context properly
        $employees = User::where('is_active', true)
            ->whereNotNull('tenant_id')
            ->select('id', 'tenant_id')
            ->orderBy('tenant_id')
            ->get()
            ->groupBy('tenant_id');

        foreach ($employees as $tenantId => $tenantEmployees) {
            app()->instance('current_tenant_id', $tenantId);

            foreach ($tenantEmployees as $employee) {
                try {
                    $service->calculateDay($employee->id, $yesterday, (int) $tenantId);
                } catch (\Throwable $e) {
                    Log::warning("CalculateDailyJourney: falha para user #{$employee->id}", [
                        'tenant_id' => $tenantId,
                        'date' => $yesterday,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('CalculateDailyJourney job failed', ['error' => $e->getMessage()]);
    }
}
