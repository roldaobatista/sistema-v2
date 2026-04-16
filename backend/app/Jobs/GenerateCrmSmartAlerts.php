<?php

namespace App\Jobs;

use App\Jobs\Middleware\SetTenantContext;
use App\Models\Tenant;
use App\Services\Crm\CrmSmartAlertGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateCrmSmartAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public int $backoff = 60;

    public function __construct()
    {
        $this->queue = 'crm';
    }

    public function handle(): void
    {
        $generator = app(CrmSmartAlertGenerator::class);

        Tenant::where('status', Tenant::STATUS_ACTIVE)->each(function ($tenant) use ($generator) {
            try {
                (new SetTenantContext($tenant->id))->handle($this, function () use ($generator, $tenant): void {
                    $generator->generateForTenant($tenant->id);
                });
            } catch (\Throwable $e) {
                Log::error('Smart alerts generation failed for tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateCrmSmartAlerts failed permanently', ['error' => $e->getMessage()]);
    }
}
