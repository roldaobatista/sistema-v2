<?php

namespace Tests\Feature\Api\V1\Webhook;

use App\Models\CrmMessage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WhatsAppWebhookControllerTest extends TestCase
{
    private Tenant $tenant;

    private Customer $customer;

    private string $webhookSecret = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        // Create whatsapp_configs entry so tenant can be resolved from instance name
        DB::table('whatsapp_configs')->insert([
            'tenant_id' => $this->tenant->id,
            'provider' => 'evolution',
            'api_url' => 'https://evo.test/api',
            'api_key' => 'test-key',
            'instance_name' => 'test-instance',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '5511999887766',
            'is_active' => true,
        ]);

        // Configure webhook secret for HMAC validation in controller
        config(['services.whatsapp.webhook_secret' => $this->webhookSecret]);
    }

    private function signedHeaders(string $payload): array
    {
        $signature = 'sha256='.hash_hmac('sha256', $payload, $this->webhookSecret);

        return [
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ];
    }

    // ─── handleMessage ──────────────────────────────────

    public function test_handle_message_creates_whatsapp_log_and_crm_message(): void
    {
        $payload = json_encode([
            'instance' => 'test-instance',
            'key' => [
                'remoteJid' => '5511999887766@s.whatsapp.net',
                'id' => 'MSG_EXT_001',
            ],
            'message' => [
                'conversation' => 'Olá, preciso de suporte!',
            ],
            'messageType' => 'conversation',
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp/messages',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signedHeaders($payload)),
            $payload,
        );

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        // WhatsappMessageLog was created
        $this->assertDatabaseHas('whatsapp_messages', [
            'tenant_id' => $this->tenant->id,
            'direction' => 'inbound',
            'phone_from' => '5511999887766',
            'message' => 'Olá, preciso de suporte!',
            'external_id' => 'MSG_EXT_001',
            'status' => 'received',
        ]);

        // CrmMessage was also created (bridged)
        $this->assertDatabaseHas('crm_messages', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'status' => 'received',
            'body' => 'Olá, preciso de suporte!',
            'from_address' => '5511999887766',
            'external_id' => 'MSG_EXT_001',
        ]);
    }

    public function test_handle_message_no_crm_message_when_customer_not_found(): void
    {
        $payload = json_encode([
            'instance' => 'test-instance',
            'key' => [
                'remoteJid' => '5500000000000@s.whatsapp.net',
                'id' => 'MSG_EXT_002',
            ],
            'message' => [
                'conversation' => 'Mensagem de número desconhecido',
            ],
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp/messages',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signedHeaders($payload)),
            $payload,
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_messages', [
            'external_id' => 'MSG_EXT_002',
        ]);

        // No CrmMessage because phone doesn't match any customer
        $this->assertDatabaseMissing('crm_messages', [
            'external_id' => 'MSG_EXT_002',
        ]);
    }

    public function test_handle_message_matches_customer_by_last_9_digits(): void
    {
        // Customer has full number with country code, webhook sends without
        $customer2 = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '5521988776655',
            'is_active' => true,
        ]);

        $payload = json_encode([
            'instance' => 'test-instance',
            'key' => [
                'remoteJid' => '21988776655@s.whatsapp.net',
                'id' => 'MSG_EXT_003',
            ],
            'message' => [
                'conversation' => 'Teste match parcial',
            ],
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp/messages',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signedHeaders($payload)),
            $payload,
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('crm_messages', [
            'customer_id' => $customer2->id,
            'external_id' => 'MSG_EXT_003',
            'channel' => 'whatsapp',
        ]);
    }

    public function test_handle_message_rejects_invalid_signature(): void
    {
        $payload = json_encode([
            'instance' => 'test-instance',
            'key' => ['remoteJid' => '5511999887766@s.whatsapp.net', 'id' => 'MSG_INVALID'],
            'message' => ['conversation' => 'Should be rejected'],
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp/messages',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Hub-Signature-256' => 'sha256=invalid_signature',
                'Content-Type' => 'application/json',
            ]),
            $payload,
        );

        $response->assertStatus(401);

        $this->assertDatabaseMissing('whatsapp_messages', [
            'external_id' => 'MSG_INVALID',
        ]);
    }

    public function test_handle_message_discards_when_no_tenant_resolved(): void
    {
        $payload = json_encode([
            'instance' => 'nonexistent-instance',
            'key' => ['remoteJid' => '5511999887766@s.whatsapp.net', 'id' => 'MSG_NO_TENANT'],
            'message' => ['conversation' => 'No tenant'],
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp/messages',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signedHeaders($payload)),
            $payload,
        );

        $response->assertStatus(200);

        $this->assertDatabaseMissing('whatsapp_messages', [
            'external_id' => 'MSG_NO_TENANT',
        ]);
    }

    // ─── handleStatus ───────────────────────────────────

    public function test_handle_status_updates_whatsapp_log_and_crm_message(): void
    {
        // Pre-create both log and CRM message (simulating an outbound message)
        $whatsappLog = WhatsappMessageLog::create([
            'tenant_id' => $this->tenant->id,
            'direction' => 'outbound',
            'phone_to' => '5511999887766',
            'message' => 'Sua OS foi concluída!',
            'message_type' => 'text',
            'status' => 'sent',
            'external_id' => 'MSG_STATUS_001',
            'sent_at' => now(),
        ]);

        $crmMessage = CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'status' => 'sent',
            'body' => 'Sua OS foi concluída!',
            'to_address' => '5511999887766',
            'external_id' => 'MSG_STATUS_001',
            'sent_at' => now(),
        ]);

        $payload = json_encode([
            'key' => ['id' => 'MSG_STATUS_001'],
            'status' => 'delivered',
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp/status',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signedHeaders($payload)),
            $payload,
        );

        $response->assertStatus(200);

        // WhatsappMessageLog updated
        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $whatsappLog->id,
            'status' => 'delivered',
        ]);

        // CrmMessage also updated
        $crmMessage->refresh();
        $this->assertEquals('delivered', $crmMessage->status);
        $this->assertNotNull($crmMessage->delivered_at);
    }

    public function test_handle_status_marks_crm_message_as_read(): void
    {
        WhatsappMessageLog::create([
            'tenant_id' => $this->tenant->id,
            'direction' => 'outbound',
            'phone_to' => '5511999887766',
            'message' => 'Lida test',
            'message_type' => 'text',
            'status' => 'delivered',
            'external_id' => 'MSG_STATUS_002',
            'sent_at' => now(),
        ]);

        CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'status' => 'delivered',
            'body' => 'Lida test',
            'to_address' => '5511999887766',
            'external_id' => 'MSG_STATUS_002',
            'sent_at' => now(),
        ]);

        $payload = json_encode([
            'key' => ['id' => 'MSG_STATUS_002'],
            'status' => 'read',
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp/status',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signedHeaders($payload)),
            $payload,
        );

        $response->assertStatus(200);

        $crm = CrmMessage::withoutGlobalScope('tenant')->where('external_id', 'MSG_STATUS_002')->first();
        $this->assertEquals('read', $crm->status);
        $this->assertNotNull($crm->read_at);
    }

    public function test_handle_status_marks_crm_message_as_failed(): void
    {
        WhatsappMessageLog::create([
            'tenant_id' => $this->tenant->id,
            'direction' => 'outbound',
            'phone_to' => '5511999887766',
            'message' => 'Fail test',
            'message_type' => 'text',
            'status' => 'sent',
            'external_id' => 'MSG_STATUS_003',
            'sent_at' => now(),
        ]);

        CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'status' => 'sent',
            'body' => 'Fail test',
            'to_address' => '5511999887766',
            'external_id' => 'MSG_STATUS_003',
            'sent_at' => now(),
        ]);

        $payload = json_encode([
            'key' => ['id' => 'MSG_STATUS_003'],
            'status' => 'failed',
            'error' => ['message' => 'Number not on WhatsApp'],
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp/status',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signedHeaders($payload)),
            $payload,
        );

        $response->assertStatus(200);

        $crm = CrmMessage::withoutGlobalScope('tenant')->where('external_id', 'MSG_STATUS_003')->first();
        $this->assertEquals('failed', $crm->status);
        $this->assertNotNull($crm->failed_at);
        $this->assertEquals('Number not on WhatsApp', $crm->error_message);
    }

    public function test_handle_status_without_crm_message_still_updates_log(): void
    {
        $log = WhatsappMessageLog::create([
            'tenant_id' => $this->tenant->id,
            'direction' => 'outbound',
            'phone_to' => '5511999887766',
            'message' => 'Solo log',
            'message_type' => 'text',
            'status' => 'sent',
            'external_id' => 'MSG_STATUS_SOLO',
            'sent_at' => now(),
        ]);

        $payload = json_encode([
            'key' => ['id' => 'MSG_STATUS_SOLO'],
            'status' => 'delivered',
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp/status',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signedHeaders($payload)),
            $payload,
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $log->id,
            'status' => 'delivered',
        ]);

        // No CrmMessage exists, no error thrown
        $this->assertDatabaseMissing('crm_messages', [
            'external_id' => 'MSG_STATUS_SOLO',
        ]);
    }

    // ─── Cross-tenant isolation ─────────────────────────

    public function test_handle_message_does_not_match_customer_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'phone' => '5511888777666',
            'is_active' => true,
        ]);

        $payload = json_encode([
            'instance' => 'test-instance',
            'key' => [
                'remoteJid' => '5511888777666@s.whatsapp.net',
                'id' => 'MSG_CROSS_TENANT',
            ],
            'message' => ['conversation' => 'Cross-tenant test'],
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp/messages',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signedHeaders($payload)),
            $payload,
        );

        $response->assertStatus(200);

        // WhatsappMessageLog created for our tenant
        $this->assertDatabaseHas('whatsapp_messages', [
            'tenant_id' => $this->tenant->id,
            'external_id' => 'MSG_CROSS_TENANT',
        ]);

        // But NO CrmMessage because customer belongs to other tenant
        $this->assertDatabaseMissing('crm_messages', [
            'external_id' => 'MSG_CROSS_TENANT',
        ]);
    }
}
