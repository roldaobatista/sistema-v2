<?php

namespace App\Jobs;

use App\Events\DocumentExpiring;
use App\Models\EmployeeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckExpiringDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(): void
    {
        $thresholds = [30, 15, 7];

        foreach ($thresholds as $days) {
            $documents = EmployeeDocument::whereNotNull('expiry_date')
                ->whereDate('expiry_date', now()->addDays($days)->toDateString())
                ->where('status', '!=', 'expired')
                ->get();

            foreach ($documents as $document) {
                try {
                    app()->instance('current_tenant_id', $document->tenant_id);
                    DocumentExpiring::dispatch($document, $days);
                } catch (\Throwable $e) {
                    Log::warning("CheckExpiringDocuments: falha para document #{$document->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('CheckExpiringDocuments job failed', ['error' => $e->getMessage()]);
    }
}
