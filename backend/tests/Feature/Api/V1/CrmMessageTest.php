<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmMessage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmMessageTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'phone' => '11999990000',
            'email' => 'customer@test.com',
        ]);
    }

    // ─── MESSAGES INDEX ───────────────────────────────

    public function test_messages_index_returns_paginated_list(): void
    {
        CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'status' => 'sent',
            'body' => 'Hello!',
            'to_address' => '5511999990000',
        ]);

        $response = $this->getJson('/api/v1/crm/messages');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_messages_index_filters_by_customer(): void
    {
        CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'channel' => 'email',
            'direction' => 'outbound',
            'status' => 'sent',
            'body' => 'Test email',
            'to_address' => 'customer@test.com',
        ]);

        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'user_id' => $this->user->id,
            'channel' => 'email',
            'direction' => 'outbound',
            'status' => 'sent',
            'body' => 'Other',
            'to_address' => 'other@test.com',
        ]);

        $response = $this->getJson("/api/v1/crm/messages?customer_id={$this->customer->id}");
        $response->assertStatus(200);
        $data = collect($response->json('data'));
        $data->each(function ($msg) {
            $this->assertEquals($this->customer->id, $msg['customer_id']);
        });
    }

    public function test_messages_index_filters_by_channel(): void
    {
        CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'status' => 'sent',
            'body' => 'WhatsApp msg',
            'to_address' => '5511999990000',
        ]);

        $response = $this->getJson('/api/v1/crm/messages?channel=whatsapp');
        $response->assertStatus(200);
    }

    // ─── SEND MESSAGE ─────────────────────────────────

    public function test_send_message_requires_customer_and_channel(): void
    {
        $response = $this->postJson('/api/v1/crm/messages/send', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id', 'channel', 'body']);
    }

    public function test_send_email_requires_subject(): void
    {
        $response = $this->postJson('/api/v1/crm/messages/send', [
            'customer_id' => $this->customer->id,
            'channel' => 'email',
            'body' => 'Email content',
            // no subject
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subject']);
    }

    // ─── TEMPLATES ────────────────────────────────────

    public function test_templates_crud_lifecycle(): void
    {
        // Create
        $createResponse = $this->postJson('/api/v1/crm/message-templates', [
            'name' => 'Welcome Template',
            'slug' => 'welcome',
            'channel' => 'whatsapp',
            'body' => 'Olá {{name}}, bem-vindo!',
            'variables' => ['name'],
        ]);
        $createResponse->assertStatus(201);
        $templateId = $createResponse->json('data.id');
        $this->assertDatabaseHas('crm_message_templates', [
            'id' => $templateId,
            'name' => 'Welcome Template',
            'slug' => 'welcome',
            'channel' => 'whatsapp',
        ]);

        // Index
        $indexResponse = $this->getJson('/api/v1/crm/message-templates');
        $indexResponse->assertStatus(200);

        // Update
        $updateResponse = $this->putJson("/api/v1/crm/message-templates/{$templateId}", [
            'name' => 'Welcome Template Updated',
        ]);
        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('crm_message_templates', [
            'id' => $templateId,
            'name' => 'Welcome Template Updated',
        ]);

        // Destroy
        $deleteResponse = $this->deleteJson("/api/v1/crm/message-templates/{$templateId}");
        $deleteResponse->assertStatus(204);
        $this->assertDatabaseMissing('crm_message_templates', ['id' => $templateId]);
    }

    public function test_template_create_validation(): void
    {
        $response = $this->postJson('/api/v1/crm/message-templates', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'slug', 'channel', 'body']);
    }

    public function test_template_channel_validation(): void
    {
        $response = $this->postJson('/api/v1/crm/message-templates', [
            'name' => 'Test',
            'slug' => 'test',
            'channel' => 'invalid_channel',
            'body' => 'Hello',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['channel']);
    }

    // ─── WEBHOOKS ─────────────────────────────────────

    public function test_whatsapp_webhook_status_update_marks_delivered(): void
    {
        // Create a message with external_id
        $message = CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'status' => 'sent',
            'body' => 'Test',
            'to_address' => '5511999990000',
            'external_id' => 'ext-123',
        ]);

        // We need to hit the webhook endpoint without sanctum middleware
        // Webhooks are outside auth, so let's test the controller logic
        $response = $this->withoutMiddleware()->postJson('/api/v1/webhooks/whatsapp', [
            'event' => 'messages.update',
            'data' => [
                [
                    'key' => ['id' => 'ext-123'],
                    'update' => ['status' => 'DELIVERY_ACK'],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('delivered', $message->fresh()->status);
    }

    public function test_whatsapp_webhook_empty_payload_returns_ignored(): void
    {
        $response = $this->withoutMiddleware()->postJson('/api/v1/webhooks/whatsapp', []);
        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'ignored']);
    }

    public function test_email_webhook_marks_read(): void
    {
        $message = CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'channel' => 'email',
            'direction' => 'outbound',
            'status' => 'delivered',
            'body' => 'Test email',
            'to_address' => 'customer@test.com',
            'external_id' => 'email-ext-456',
        ]);

        $response = $this->withoutMiddleware()->postJson('/api/v1/webhooks/email', [
            [
                'type' => 'opened',
                'message_id' => 'email-ext-456',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('read', $message->fresh()->status);
    }

    public function test_email_webhook_marks_failed_on_bounce(): void
    {
        $message = CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'channel' => 'email',
            'direction' => 'outbound',
            'status' => 'sent',
            'body' => 'Test email',
            'to_address' => 'bad@test.com',
            'external_id' => 'email-ext-789',
        ]);

        $response = $this->withoutMiddleware()->postJson('/api/v1/webhooks/email', [
            [
                'type' => 'bounced',
                'message_id' => 'email-ext-789',
                'reason' => 'Mailbox full',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('failed', $message->fresh()->status);
        $this->assertEquals('Mailbox full', $message->fresh()->error_message);
    }
}
