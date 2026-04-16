<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\AlertEngineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunAlertEngine extends Command
{
    protected $signature = 'alerts:run {--tenant= : ID do tenant específico}';

    protected $description = 'Executa o motor de alertas para todos os tenants (ou um específico)';

    public function handle(AlertEngineService $engine): int
    {
        $tenantId = $this->option('tenant');
        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::where('status', Tenant::STATUS_ACTIVE)->get();

        foreach ($tenants as $tenant) {
            app()->instance('current_tenant_id', $tenant->id);

            $this->info("Processando tenant: {$tenant->name} (ID: {$tenant->id})");

            try {
                $results = $engine->runAllChecks($tenant->id);
                $total = array_sum($results);
                $this->info("  → {$total} alertas gerados.");

                foreach ($results as $type => $count) {
                    if ($count > 0) {
                        $this->line("    - {$type}: {$count}");
                    }
                }

                $escalated = $engine->runEscalationChecks($tenant->id);
                if ($escalated > 0) {
                    $this->info("  → {$escalated} alerta(s) escalado(s).");
                }
            } catch (\Throwable $e) {
                Log::error("RunAlertEngine: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
                $this->error("  → Erro: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
