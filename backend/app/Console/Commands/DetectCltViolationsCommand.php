<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\CltViolationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DetectCltViolationsCommand extends Command
{
    protected $signature = 'hr:detect-clt-violations {--date= : The date to check for violations (Y-m-d)}';

    protected $description = 'Detect structural CLT violations for work journeys';

    public function handle(CltViolationService $service)
    {
        $date = $this->option('date') ?: Carbon::yesterday()->format('Y-m-d');
        $this->info("Starting CLT Violation Detection for date: {$date}");

        $tenants = Tenant::where('status', Tenant::STATUS_ACTIVE)->get();

        foreach ($tenants as $tenant) {
            app()->instance('current_tenant_id', $tenant->id);
            $this->info("Processing tenant: {$tenant->name}");

            $users = User::whereHas('tenants', function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id);
            })->where('is_active', true)->get();

            foreach ($users as $user) {
                try {
                    $service->analyzeDay($user->id, $date, $tenant->id);
                } catch (\Throwable $e) {
                    Log::error("Failed to analyze CLT violations for User ID {$user->id} in Tenant {$tenant->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info('Completed CLT Violation Detection.');

        return 0;
    }
}
