<?php

namespace App\Console\Commands;

use App\Models\JourneyRule;
use App\Models\User;
use App\Services\HourBankExpiryService;
use Illuminate\Console\Command;

class ProcessHourBankExpiryCommand extends Command
{
    protected $signature = 'hour-bank:process-expiry {--dry-run : Show what would expire without processing}';

    protected $description = 'Process hour bank expiry for all tenants (Art. 59 §§2,5,6 CLT)';

    public function handle(HourBankExpiryService $service): int
    {
        $dryRun = $this->option('dry-run');

        $rules = JourneyRule::where('uses_hour_bank', true)
            ->where('is_default', true)
            ->get();

        if ($rules->isEmpty()) {
            $this->info('No tenants with hour bank enabled.');

            return self::SUCCESS;
        }

        $totalProcessed = 0;
        $totalExpired = 0;

        foreach ($rules as $rule) {
            $users = User::where('tenant_id', $rule->tenant_id)
                ->where('is_active', true)
                ->get();

            foreach ($users as $user) {
                if ($dryRun) {
                    $cutoff = $service->getExpiryDate($rule);
                    $balance = $service->getExpiringBalance($user->id, $rule->tenant_id, $cutoff);
                    if ($balance > 0) {
                        $this->line("[DRY-RUN] User #{$user->id} ({$user->name}): {$balance}h would expire (cutoff: {$cutoff->toDateString()})");
                        $totalExpired += $balance;
                    }
                } else {
                    $result = $service->processExpiry($user->id, $rule->tenant_id);
                    if (($result['expired_hours'] ?? 0) > 0) {
                        $this->line("User #{$user->id} ({$user->name}): {$result['expired_hours']}h expired");
                        $totalExpired += $result['expired_hours'];
                    }
                }
                $totalProcessed++;
            }
        }

        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("{$prefix}Processed {$totalProcessed} users. Total expired: {$totalExpired}h.");

        return self::SUCCESS;
    }
}
