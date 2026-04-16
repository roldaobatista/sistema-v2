<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\Tenant;
use App\Notifications\QuoteStatusNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QuoteExpirationAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct()
    {
        $this->queue = 'alerts';
    }

    public function handle(): void
    {
        $alertDays = 3;

        // Iterate tenants to set context for BelongsToTenant global scope
        $tenantIds = Tenant::where('status', Tenant::STATUS_ACTIVE)->pluck('id');

        foreach ($tenantIds as $tenantId) {
            try {
                app()->instance('current_tenant_id', $tenantId);

                $quotes = Quote::whereIn('status', Quote::expirableStatuses())
                    ->whereNotNull('valid_until')
                    ->whereDate('valid_until', '<=', today()->addDays($alertDays))
                    ->whereDate('valid_until', '>=', today())
                    ->with(['seller', 'customer'])
                    ->get();

                foreach ($quotes as $quote) {
                    try {
                        $daysLeft = today()->diffInDays($quote->valid_until, false);

                        AuditLog::log(
                            'expiration_alert',
                            "Orçamento {$quote->quote_number} expira em {$daysLeft} dia(s)",
                            $quote
                        );

                        // Notificar vendedor sobre expiração iminente
                        if ($quote->seller) {
                            $notification = new QuoteStatusNotification($quote, 'expired', "Expira em {$daysLeft} dia(s)");
                            $notification->persistToDatabase($tenantId, $quote->seller->id);

                            try {
                                $quote->seller->notify($notification);
                            } catch (\Throwable $mailError) {
                                Log::warning('Quote expiration email failed', ['quote_id' => $quote->id, 'error' => $mailError->getMessage()]);
                            }
                        }

                        Log::info('Quote expiration alert', [
                            'quote_id' => $quote->id,
                            'quote_number' => $quote->quote_number,
                            'days_left' => $daysLeft,
                            'valid_until' => $quote->valid_until->format('Y-m-d'),
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Quote expiration alert failed', [
                            'quote_id' => $quote->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("QuoteExpirationAlertJob: falha no tenant {$tenantId}", ['error' => $e->getMessage()]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('QuoteExpirationAlertJob failed permanently', ['error' => $e->getMessage()]);
    }
}
