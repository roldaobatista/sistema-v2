<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\Tenant;
use App\Services\Email\EmailSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct()
    {
        $this->queue = 'email-send';
    }

    public function handle(EmailSendService $emailService): void
    {
        // Iterate tenants to set context for BelongsToTenant global scope
        $tenantIds = Tenant::where('status', Tenant::STATUS_ACTIVE)->pluck('id');

        foreach ($tenantIds as $tenantId) {
            try {
                app()->instance('current_tenant_id', $tenantId);

                $emails = Email::where('status', 'scheduled')
                    ->where('scheduled_at', '<=', now())
                    ->get();

                foreach ($emails as $email) {
                    try {
                        Log::info("Sending scheduled email ID: {$email->id}");
                        $emailService->deliver($email);
                    } catch (\Throwable $e) {
                        Log::error("Failed to send scheduled email ID: {$email->id}", [
                            'error' => $e->getMessage(),
                            'tenant_id' => $tenantId,
                        ]);
                        $email->update(['status' => 'failed']);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("SendScheduledEmails: tenant {$tenantId} failed", ['error' => $e->getMessage()]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendScheduledEmails failed permanently', ['error' => $e->getMessage()]);
    }
}
