<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\VerifyFiscalWebhookSecret;
use App\Services\Fiscal\FiscalWebhookCallbackService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * FiscalWebhookCallbackController — webhook publico (sem auth) com protecao HMAC
 * via middleware VerifyFiscalWebhookSecret + FormRequest::authorize() que valida
 * header X-Fiscal-Webhook-Secret.
 *
 * Os testes rodam em non-production sem secret configurado, caso em que tanto
 * o middleware quanto o FormRequest permitem (matching behavior documentado).
 */
class FiscalWebhookCallbackControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Em testes, nao ha secret configurado → middleware e FormRequest permitem
        // Mas por seguranca withoutMiddleware garante isolamento do teste
        $this->withoutMiddleware([VerifyFiscalWebhookSecret::class]);

        // Garantir env nao-producao para bypass de autorize()
        config(['app.env' => 'testing']);
        config(['services.fiscal_external.webhook_secret' => null]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rejects_empty_payload_with_422(): void
    {
        $response = $this->postJson('/api/v1/fiscal/webhook', []);

        $response->assertStatus(422);
    }

    public function test_delegates_payload_to_callback_service(): void
    {
        /** @var FiscalWebhookCallbackService&MockInterface $serviceMock */
        $serviceMock = Mockery::mock(FiscalWebhookCallbackService::class);
        $serviceMock->shouldReceive('process')
            ->once()
            ->with(Mockery::on(fn (array $p) => $p['ref'] === 'nfe_xyz_123'))
            ->andReturn([
                'processed' => true,
                'note_id' => 42,
                'message' => 'ok',
                'status' => 'autorizado',
            ]);

        $this->app->instance(FiscalWebhookCallbackService::class, $serviceMock);

        $response = $this->postJson('/api/v1/fiscal/webhook', [
            'ref' => 'nfe_xyz_123',
            'status' => 'autorizado',
            'chave_nfe' => str_repeat('1', 44),
            'numero' => '123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.received', true)
            ->assertJsonPath('data.processed', true)
            ->assertJsonPath('data.note_id', 42);
    }

    public function test_returns_404_when_service_cannot_locate_note(): void
    {
        /** @var FiscalWebhookCallbackService&MockInterface $serviceMock */
        $serviceMock = Mockery::mock(FiscalWebhookCallbackService::class);
        $serviceMock->shouldReceive('process')
            ->once()
            ->andReturn([
                'processed' => false,
                'note_id' => null,
                'message' => 'Nota nao encontrada para o ref',
            ]);

        $this->app->instance(FiscalWebhookCallbackService::class, $serviceMock);

        $response = $this->postJson('/api/v1/fiscal/webhook', [
            'ref' => 'unknown_ref_xxx',
            'status' => 'autorizado',
        ]);

        // Controller retorna 404 quando note_id nulo + processed=false
        $response->assertStatus(404)
            ->assertJsonPath('data.received', true)
            ->assertJsonPath('data.processed', false);
    }

    public function test_returns_200_received_but_not_processed_when_service_marks_skipped(): void
    {
        /** @var FiscalWebhookCallbackService&MockInterface $serviceMock */
        $serviceMock = Mockery::mock(FiscalWebhookCallbackService::class);
        $serviceMock->shouldReceive('process')
            ->once()
            ->andReturn([
                'processed' => false,
                'note_id' => 7, // encontrou mas nao processou (duplicado, ja autorizada, etc.)
                'message' => 'Nota ja autorizada — ignorando',
            ]);

        $this->app->instance(FiscalWebhookCallbackService::class, $serviceMock);

        $response = $this->postJson('/api/v1/fiscal/webhook', [
            'ref' => 'existing_ref',
            'status' => 'autorizado',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.received', true)
            ->assertJsonPath('data.processed', false);
    }
}
