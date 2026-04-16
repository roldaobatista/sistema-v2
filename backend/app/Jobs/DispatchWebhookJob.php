<?php

namespace App\Jobs;

use App\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public Webhook $webhook,
        public string $event,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $body = [
            'event' => $this->event,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->payload,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'X-Webhook-Event' => $this->event,
        ];

        if ($this->webhook->secret) {
            $signature = hash_hmac('sha256', json_encode($body), $this->webhook->secret);
            $headers['X-Webhook-Signature'] = $signature;
        }

        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->post($this->webhook->url, $body);

        $this->webhook->update(['last_triggered_at' => now()]);

        $this->webhook->logs()->create([
            'tenant_id' => $this->webhook->tenant_id,
            'event' => $this->event,
            'url' => $this->webhook->url,
            'payload' => $body,
            'response_status' => $response->status(),
            'response_body' => mb_substr($response->body(), 0, 2000),
            'success' => $response->successful(),
        ]);

        if ($response->failed()) {
            $this->webhook->increment('failure_count');

            Log::warning("Webhook #{$this->webhook->id} failed for event {$this->event}", [
                'status' => $response->status(),
                'url' => $this->webhook->url,
            ]);

            // Auto-disable after 10 consecutive failures
            if ($this->webhook->fresh()->failure_count >= 10) {
                $this->webhook->update(['is_active' => false]);
                Log::error("Webhook #{$this->webhook->id} auto-disabled after 10 failures");
            }

            $this->fail(new \RuntimeException("Webhook request failed with status {$response->status()}"));
        } else {
            // Reset failure count on success
            if ($this->webhook->failure_count > 0) {
                $this->webhook->update(['failure_count' => 0]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("DispatchWebhookJob failed permanently for webhook #{$this->webhook->id}", [
            'event' => $this->event,
            'error' => $exception->getMessage(),
        ]);
    }
}
