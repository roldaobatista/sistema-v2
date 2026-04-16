<?php

namespace App\Jobs;

use App\Models\ESocialEvent;
use App\Services\ESocial\ESocialTransmissionService;
use App\Services\Integration\ExponentialBackoff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronous job for transmitting eSocial event batches.
 *
 * Uses exponential backoff for retries and respects the Circuit Breaker.
 */
class ProcessESocialBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $maxExceptions = 3;

    public int $timeout = 60;

    /**
     * @param  array<int>  $eventIds
     */
    public function __construct(
        private readonly array $eventIds,
        private readonly int $tenantId,
    ) {}

    /**
     * Exponential backoff delays in seconds.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return ExponentialBackoff::sequence(
            maxRetries: $this->tries,
            baseDelay: (int) config('esocial.retry.base_delay', 5),
            maxDelay: (int) config('esocial.retry.max_delay', 300),
        );
    }

    public function handle(ESocialTransmissionService $transmissionService): void
    {
        Log::info('ProcessESocialBatchJob: starting', [
            'event_ids' => $this->eventIds,
            'tenant_id' => $this->tenantId,
            'attempt' => $this->attempts(),
        ]);

        $result = $transmissionService->transmitBatch($this->eventIds);

        Log::info('ProcessESocialBatchJob: batch transmitted', [
            'batch_id' => $result['batch_id'],
            'protocol_number' => $result['protocol_number'],
            'events_sent' => $result['events_sent'],
        ]);

        // Increment retry_count on all events for tracking
        ESocialEvent::whereIn('id', $this->eventIds)
            ->increment('retry_count');
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('ProcessESocialBatchJob: permanently failed', [
            'event_ids' => $this->eventIds,
            'tenant_id' => $this->tenantId,
            'error' => $exception?->getMessage(),
        ]);

        // Mark all events as failed
        ESocialEvent::whereIn('id', $this->eventIds)
            ->update([
                'status' => 'rejected',
                'error_message' => 'Transmissão falhou após todas as tentativas: '.($exception?->getMessage() ?? 'unknown'),
            ]);
    }
}
