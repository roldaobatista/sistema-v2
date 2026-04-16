<?php

namespace Tests\Feature\Api\V1\Webhooks;

use App\Events\PaymentWebhookProcessed;
use App\Models\AccountReceivable;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => 1,
            'amount' => 150.00,
            'payment_method' => 'pix',
            'payment_date' => now(),
            'external_id' => 'PAY-TEST-001',
            'status' => 'pending',
        ]);
    }

    public function test_webhook_confirms_payment_successfully(): void
    {
        Event::fake();

        $response = $this->postJson('/api/v1/webhooks/payment', [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'PAY-TEST-001',
                'status' => 'CONFIRMED',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'confirmed');

        $this->payment->refresh();
        $this->assertEquals('confirmed', $this->payment->status);
        $this->assertNotNull($this->payment->paid_at);

        Event::assertDispatched(PaymentWebhookProcessed::class);
    }

    public function test_webhook_invalid_signature_returns_401(): void
    {
        config(['services.payment.webhook_secret' => 'my-secret']);

        $response = $this->postJson('/api/v1/webhooks/payment', [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => ['id' => 'PAY-TEST-001'],
        ], [
            'X-Webhook-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_invalid_payload_returns_422(): void
    {
        $response = $this->postJson('/api/v1/webhooks/payment', [
            'event' => 'PAYMENT_CONFIRMED',
            // missing payment.id
        ]);

        $response->assertStatus(422);
    }

    public function test_webhook_payment_not_found_returns_404(): void
    {
        $response = $this->postJson('/api/v1/webhooks/payment', [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => ['id' => 'NONEXISTENT-123'],
        ]);

        $response->assertStatus(404);
    }

    public function test_webhook_idempotency_already_confirmed(): void
    {
        $this->payment->update(['status' => 'confirmed', 'paid_at' => now()]);

        $response = $this->postJson('/api/v1/webhooks/payment', [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => ['id' => 'PAY-TEST-001'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'already_processed');
    }

    public function test_webhook_cancellation_updates_status(): void
    {
        $response = $this->postJson('/api/v1/webhooks/payment', [
            'event' => 'PAYMENT_CANCELLED',
            'payment' => ['id' => 'PAY-TEST-001'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');

        $this->payment->refresh();
        $this->assertEquals('cancelled', $this->payment->status);
    }

    public function test_webhook_overdue_updates_status(): void
    {
        $response = $this->postJson('/api/v1/webhooks/payment', [
            'event' => 'PAYMENT_OVERDUE',
            'payment' => ['id' => 'PAY-TEST-001'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'overdue');
    }

    public function test_webhook_stores_gateway_response(): void
    {
        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'PAY-TEST-001',
                'status' => 'CONFIRMED',
                'value' => 150.00,
            ],
        ];

        $this->postJson('/api/v1/webhooks/payment', $payload)->assertOk();

        $this->payment->refresh();
        $this->assertNotNull($this->payment->gateway_response);
        $this->assertIsArray($this->payment->gateway_response);
    }
}
