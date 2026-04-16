<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\PostServiceSurveyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPostOsSurveys extends Command
{
    protected $signature = 'surveys:send-post-os {--tenant= : ID do tenant específico}';

    protected $description = 'Envia pesquisa de satisfação para OS concluídas nas últimas 24h';

    public function handle(PostServiceSurveyService $service): int
    {
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::where('status', Tenant::STATUS_ACTIVE)->get();

        $totalSent = 0;

        foreach ($tenants as $tenant) {
            app()->instance('current_tenant_id', $tenant->id);

            try {
                $sent = $service->processForTenant($tenant);
                $totalSent += $sent;

                if ($sent > 0) {
                    $this->info("Tenant #{$tenant->id} ({$tenant->name}): {$sent} pesquisas enviadas");
                }
            } catch (\Throwable $e) {
                Log::error("SendPostOsSurveys: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
                $this->error("Tenant #{$tenant->id}: {$e->getMessage()}");
            }
        }

        $this->info("Total: {$totalSent} pesquisas de satisfação enviadas.");

        return self::SUCCESS;
    }
}
