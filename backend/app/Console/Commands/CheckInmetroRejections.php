<?php

namespace App\Console\Commands;

use App\Models\InmetroBaseConfig;
use App\Models\InmetroInstrument;
use App\Models\Tenant;
use App\Services\InmetroNotificationService;
use App\Services\InmetroXmlImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckInmetroRejections extends Command
{
    protected $signature = 'inmetro:check-rejections
        {--tenant= : Specific tenant ID}';

    protected $description = 'Fast check for newly rejected instruments. Runs every 4h for urgent commercial alerts.';

    public function handle(
        InmetroXmlImportService $xmlService,
        InmetroNotificationService $notificationService,
    ): int {
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            try {
                app()->instance('current_tenant_id', $tenant->id);
                $config = InmetroBaseConfig::where('tenant_id', $tenant->id)->first();
                $lastCheck = $config?->last_rejection_check_at;

                $this->info("═══ Checking rejections for: {$tenant->name} ═══");

                // Quick re-import to capture latest data
                $importConfig = $tenant->inmetro_config ?? InmetroXmlImportService::defaultConfig();
                $ufs = $importConfig['monitored_ufs'] ?? ['MT'];

                foreach ($ufs as $uf) {
                    try {
                        $result = $xmlService->importAllForConfig($tenant->id, [$uf], null);
                        $gt = $result['results']['grand_totals'] ?? [];
                        $this->info("  {$uf}: {$gt['instruments_created']} new, {$gt['instruments_updated']} updated");
                    } catch (\Throwable $e) {
                        Log::warning("CheckInmetroRejections: falha ao importar UF {$uf} para tenant #{$tenant->id}", ['error' => $e->getMessage()]);
                        $this->warn("  ⚠ {$uf}: import failed - {$e->getMessage()}");
                    }
                }

                // Find instruments that became rejected since last check
                $query = InmetroInstrument::query()
                    ->whereHas('location.owner', fn ($q) => $q->where('tenant_id', $tenant->id))
                    ->where('current_status', 'rejected');

                if ($lastCheck) {
                    $query->where('updated_at', '>', $lastCheck);
                }

                $newRejections = $query->count();

                if ($newRejections > 0) {
                    $this->warn("  🔴 {$newRejections} NEW REJECTIONS detected!");
                    $notifStats = $notificationService->checkAndNotifyRejections($tenant->id);
                    $this->info("  Notifications sent: {$notifStats}");
                } else {
                    $this->info('  ✅ No new rejections.');
                }

                // Update last check timestamp
                if ($config) {
                    $config->update(['last_rejection_check_at' => now()]);
                } else {
                    InmetroBaseConfig::create([
                        'tenant_id' => $tenant->id,
                        'last_rejection_check_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error("CheckInmetroRejections: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
                $this->error("Tenant #{$tenant->id}: {$e->getMessage()}");
            }
        }

        $this->info('✅ Rejection check completed.');

        return self::SUCCESS;
    }
}
