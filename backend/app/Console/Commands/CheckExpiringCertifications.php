<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Journey\TechnicianEligibilityService;
use Illuminate\Console\Command;

class CheckExpiringCertifications extends Command
{
    protected $signature = 'journey:check-certifications {--days=30 : Days ahead to check}';

    protected $description = 'Check for expiring technician certifications and refresh statuses';

    public function handle(TechnicianEligibilityService $service): int
    {
        $days = (int) $this->option('days');

        $tenants = Tenant::all();
        $totalExpiring = 0;
        $totalUpdated = 0;

        foreach ($tenants as $tenant) {
            $updated = $service->refreshAllStatuses($tenant->id);
            $expiring = $service->getExpiringCertifications($tenant->id, $days);

            $totalUpdated += $updated;
            $totalExpiring += $expiring->count();

            if ($expiring->isNotEmpty()) {
                $this->info("Tenant #{$tenant->id}: {$expiring->count()} certificação(ões) vencendo em {$days} dias");
            }
        }

        $this->info("Total: {$totalExpiring} vencendo, {$totalUpdated} status atualizados.");

        return self::SUCCESS;
    }
}
