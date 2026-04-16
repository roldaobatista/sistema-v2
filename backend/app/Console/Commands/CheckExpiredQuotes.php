<?php

namespace App\Console\Commands;

use App\Enums\QuoteStatus;
use App\Models\AuditLog;
use App\Models\Quote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExpiredQuotes extends Command
{
    protected $signature = 'quotes:check-expired';

    protected $description = 'Mark quotations as expired when valid_until date has passed';

    public function handle(): int
    {
        $count = 0;

        Quote::withoutGlobalScopes()
            ->whereIn('status', Quote::expirableStatuses())
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', today())
            ->chunkById(200, function ($quotes) use (&$count) {
                foreach ($quotes as $quote) {
                    try {
                        app()->instance('current_tenant_id', $quote->tenant_id);
                        $quote->update(['status' => QuoteStatus::EXPIRED->value]);
                        AuditLog::log('status_changed', "Orçamento {$quote->quote_number} expirado automaticamente", $quote);
                        $count++;
                    } catch (\Throwable $e) {
                        Log::warning("CheckExpiredQuotes: falha ao expirar quote #{$quote->id}", ['error' => $e->getMessage()]);
                    }
                }
            });

        $this->info("Marked {$count} quote(s) as expired.");

        return self::SUCCESS;
    }
}
