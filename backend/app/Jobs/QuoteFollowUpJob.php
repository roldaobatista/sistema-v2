<?php

namespace App\Jobs;

use App\Enums\QuoteStatus;
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

class QuoteFollowUpJob implements ShouldQueue
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
        $daysThreshold = 3;
        $maxFollowups = 3;

        // Iterate tenants to set context for BelongsToTenant global scope
        $tenantIds = Tenant::where('status', Tenant::STATUS_ACTIVE)->pluck('id');

        foreach ($tenantIds as $tenantId) {
            try {
                app()->instance('current_tenant_id', $tenantId);

                $quotes = Quote::where('status', QuoteStatus::SENT)
                    ->where('followup_count', '<', $maxFollowups)
                    ->where(function ($q) use ($daysThreshold) {
                        $q->whereNull('last_followup_at')
                            ->where('sent_at', '<=', now()->subDays($daysThreshold))
                            ->orWhere('last_followup_at', '<=', now()->subDays($daysThreshold));
                    })
                    ->with(['seller', 'customer'])
                    ->get();

                foreach ($quotes as $quote) {
                    try {
                        // Disable Auditable events during internal updates to prevent noisy 'updated' logs
                        Quote::withoutEvents(function () use ($quote) {
                            $quote->increment('followup_count');
                            $quote->update(['last_followup_at' => now()]);
                        });
                        $quote->refresh();

                        AuditLog::log(
                            'followup_reminder',
                            "Follow-up #{$quote->followup_count} para orçamento {$quote->quote_number}",
                            $quote
                        );

                        // Notificar vendedor para fazer follow-up
                        if ($quote->seller) {
                            $notification = new QuoteStatusNotification(
                                $quote,
                                'sent',
                                "Follow-up #{$quote->followup_count} — cliente ainda não respondeu"
                            );
                            $notification->persistToDatabase($tenantId, $quote->seller->id);

                            try {
                                $quote->seller->notify($notification);
                            } catch (\Throwable $mailError) {
                                Log::warning('Quote follow-up email failed', ['quote_id' => $quote->id, 'error' => $mailError->getMessage()]);
                            }
                        }

                        Log::info('Quote follow-up sent', [
                            'quote_id' => $quote->id,
                            'quote_number' => $quote->quote_number,
                            'followup_count' => $quote->followup_count,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Quote follow-up failed', [
                            'quote_id' => $quote->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("QuoteFollowUpJob: falha no tenant {$tenantId}", ['error' => $e->getMessage()]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('QuoteFollowUpJob failed permanently', ['error' => $e->getMessage()]);
    }
}
