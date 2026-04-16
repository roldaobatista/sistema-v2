<?php

namespace Tests\Feature;

use App\Services\Fiscal\FiscalWebhookCallbackService;
use Mockery;
use Tests\TestCase;

class FiscalWebhookSecurityTest extends TestCase
{
    public function test_fiscal_webhook_accepts_only_secret_in_header(): void
    {
        config(['services.fiscal_external.webhook_secret' => 'secret-123']);

        $service = Mockery::mock(FiscalWebhookCallbackService::class);
        $service->shouldReceive('process')
            ->once()
            ->andReturn([
                'processed' => true,
                'note_id' => 10,
                'message' => 'Webhook processado',
                'status' => 'authorized',
            ]);

        $this->app->instance(FiscalWebhookCallbackService::class, $service);

        $this->postJson('/api/v1/fiscal/webhook', ['ref' => 'NF-1'])
            ->assertForbidden();

        $this->postJson('/api/v1/fiscal/webhook?webhook_secret=secret-123', ['ref' => 'NF-1'])
            ->assertForbidden();

        $this->postJson(
            '/api/v1/fiscal/webhook',
            ['ref' => 'NF-1', 'webhook_secret' => 'secret-123'],
            ['X-Fiscal-Webhook-Secret' => 'secret-123']
        )
            ->assertOk()
            ->assertJsonPath('data.processed', true)
            ->assertJsonPath('data.note_id', 10);
    }
}
