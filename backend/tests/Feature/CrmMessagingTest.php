<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmDeal;
use App\Models\CrmMessage;
use App\Models\CrmMessageTemplate;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmMessagingTest extends TestCase
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
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '11999887766',
            'email' => 'cliente@empresa.com',
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    // ─── Message API ────────────────────────────────────

    public function test_list_messages(): void
    {
        CrmMessage::factory()->count(3)->whatsapp()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson('/api/v1/crm/messages');

        $response->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_list_messages_filtered_by_channel(): void
    {
        CrmMessage::factory()->count(2)->whatsapp()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        CrmMessage::factory()->count(3)->email()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->getJson('/api/v1/crm/messages?channel=whatsapp')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->getJson('/api/v1/crm/messages?channel=email')
            ->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_send_whatsapp_without_evolution_config(): void
    {
        config(['services.evolution.url' => null]);

        $response = $this->postJson('/api/v1/crm/messages/send', [
            'customer_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'body' => 'Olá, tudo bem?',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('crm_messages', [
            'customer_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'status' => 'failed',
        ]);
    }

    public function test_send_whatsapp_with_mock_evolution(): void
    {
        config([
            'services.evolution.url' => 'http://fake-evolution',
            'services.evolution.api_key' => 'test-key',
            'services.evolution.instance' => 'test',
        ]);

        Http::fake([
            'fake-evolution/*' => Http::response([
                'key' => ['id' => 'external-msg-123'],
                'status' => 'PENDING',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/crm/messages/send', [
            'customer_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'body' => 'Olá, tudo bem?',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.status', 'sent');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://fake-evolution/message/sendText/test'
                && ($request['number'] ?? null) === '5511999887766';
        });

        $this->assertDatabaseHas('crm_messages', [
            'customer_id' => $this->customer->id,
            'external_id' => 'external-msg-123',
            'status' => 'sent',
            'to_address' => '5511999887766',
        ]);

        // Should log to timeline
        $this->assertDatabaseHas('crm_activities', [
            'customer_id' => $this->customer->id,
            'type' => 'whatsapp',
            'is_automated' => true,
        ]);

        // Customer last_contact_at should update
        $this->customer->refresh();
        $this->assertTrue($this->customer->last_contact_at->isToday());
    }

    public function test_send_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/crm/messages/send', [
            'customer_id' => $this->customer->id,
            'channel' => 'email',
            'subject' => 'Proposta Comercial',
            'body' => '<p>Segue proposta em anexo.</p>',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.channel', 'email');

        $this->assertDatabaseHas('crm_messages', [
            'customer_id' => $this->customer->id,
            'channel' => 'email',
            'subject' => 'Proposta Comercial',
            'to_address' => 'cliente@empresa.com',
        ]);

        $this->assertDatabaseHas('crm_activities', [
            'customer_id' => $this->customer->id,
            'type' => 'email',
        ]);
    }

    public function test_send_requires_subject_for_email(): void
    {
        $this->postJson('/api/v1/crm/messages/send', [
            'customer_id' => $this->customer->id,
            'channel' => 'email',
            'body' => 'Corpo sem assunto',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('subject');
    }

    // ─── Templates ──────────────────────────────────────

    public function test_list_templates(): void
    {
        CrmMessageTemplate::factory()->count(2)->whatsapp()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        CrmMessageTemplate::factory()->email()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->getJson('/api/v1/crm/message-templates')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/v1/crm/message-templates?channel=whatsapp')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_templates_can_include_inactive_for_management(): void
    {
        CrmMessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Template ativo',
            'is_active' => true,
        ]);

        CrmMessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Template inativo',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/crm/message-templates')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/crm/message-templates?include_inactive=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_create_template(): void
    {
        $response = $this->postJson('/api/v1/crm/message-templates', [
            'name' => 'Boas-vindas',
            'slug' => 'boas-vindas',
            'channel' => 'whatsapp',
            'body' => 'Olá {{nome}}, seja bem-vindo!',
            'variables' => [
                ['name' => 'nome', 'description' => 'Nome do cliente'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Boas-vindas')
            ->assertJsonPath('data.slug', 'boas-vindas');

        $this->assertDatabaseHas('crm_message_templates', [
            'tenant_id' => $this->tenant->id,
            'slug' => 'boas-vindas',
        ]);
    }

    public function test_update_template(): void
    {
        $template = CrmMessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original',
        ]);

        $this->putJson("/api/v1/crm/message-templates/{$template->id}", [
            'name' => 'Atualizado',
            'body' => 'Novo corpo {{nome}}',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Atualizado');
    }

    public function test_send_rejects_template_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $template = CrmMessageTemplate::factory()->create([
            'tenant_id' => $otherTenant->id,
            'channel' => 'whatsapp',
        ]);

        $response = $this->postJson('/api/v1/crm/messages/send', [
            'customer_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'body' => 'Teste',
            'template_id' => $template->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['template_id']);
    }

    public function test_other_tenant_template_is_not_accessible_for_update(): void
    {
        $otherTenant = Tenant::factory()->create();
        $template = CrmMessageTemplate::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Outro tenant',
        ]);

        $this->putJson("/api/v1/crm/message-templates/{$template->id}", [
            'name' => 'Nao deveria atualizar',
        ])->assertNotFound();
    }

    public function test_delete_template(): void
    {
        $template = CrmMessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->deleteJson("/api/v1/crm/message-templates/{$template->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('crm_message_templates', ['id' => $template->id]);
    }

    public function test_send_from_template(): void
    {
        config([
            'services.evolution.url' => 'http://fake-evolution',
            'services.evolution.api_key' => 'test-key',
            'services.evolution.instance' => 'test',
        ]);

        Http::fake([
            'fake-evolution/*' => Http::response([
                'key' => ['id' => 'tmpl-msg-456'],
            ], 200),
        ]);

        $template = CrmMessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'whatsapp',
            'body' => 'Olá {{nome}}, seu equipamento está pronto!',
        ]);

        $response = $this->postJson('/api/v1/crm/messages/send', [
            'customer_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'body' => 'ignored when template is used',
            'template_id' => $template->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'sent');

        $msg = CrmMessage::first();
        $this->assertStringContains('Olá '.$this->customer->name, $msg->body);
    }

    // ─── Webhooks ────────────────────────────────────────

    public function test_whatsapp_webhook_status_update(): void
    {
        $message = CrmMessage::factory()->whatsapp()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'external_id' => 'wamid-12345',
            'status' => 'sent',
        ]);

        $response = $this->postJson('/api/v1/webhooks/whatsapp', [
            'event' => 'messages.update',
            'data' => [
                [
                    'key' => ['id' => 'wamid-12345'],
                    'update' => ['status' => 'DELIVERY_ACK'],
                ],
            ],
        ]);

        $response->assertOk();

        $message->refresh();
        $this->assertEquals('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);
    }

    public function test_whatsapp_webhook_read_status(): void
    {
        $message = CrmMessage::factory()->whatsapp()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'external_id' => 'wamid-67890',
            'status' => 'delivered',
        ]);

        $this->postJson('/api/v1/webhooks/whatsapp', [
            'event' => 'messages.update',
            'data' => [
                [
                    'key' => ['id' => 'wamid-67890'],
                    'update' => ['status' => 'READ'],
                ],
            ],
        ])->assertOk();

        $message->refresh();
        $this->assertEquals('read', $message->status);
        $this->assertNotNull($message->read_at);
    }

    public function test_whatsapp_webhook_inbound_message(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->user->id,
        ]);

        // FIX-1: Tenant isolation requires a prior outbound message for phone lookup
        CrmMessage::factory()->whatsapp()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'deal_id' => $deal->id,
            'user_id' => $this->user->id,
            'to_address' => '11999887766',
            'direction' => CrmMessage::DIRECTION_OUTBOUND,
        ]);

        $this->postJson('/api/v1/webhooks/whatsapp', [
            'event' => 'messages.upsert',
            'data' => [
                [
                    'key' => [
                        'remoteJid' => '5511999887766@s.whatsapp.net',
                        'fromMe' => false,
                        'id' => 'inbound-id-001',
                    ],
                    'message' => [
                        'conversation' => 'Preciso fazer calibração da balança',
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('crm_messages', [
            'customer_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'external_id' => 'inbound-id-001',
            'deal_id' => $deal->id,
            'user_id' => $this->user->id,
        ]);

        // Should log to timeline
        $this->assertDatabaseHas('crm_activities', [
            'customer_id' => $this->customer->id,
            'deal_id' => $deal->id,
            'user_id' => $this->user->id,
            'type' => 'whatsapp',
        ]);
    }

    public function test_email_webhook_delivery(): void
    {
        $message = CrmMessage::factory()->email()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'external_id' => 'email-msg-id-001',
            'status' => 'sent',
        ]);

        $this->postJson('/api/v1/webhooks/email', [
            ['type' => 'delivered', 'message_id' => 'email-msg-id-001'],
        ])->assertOk();

        $message->refresh();
        $this->assertEquals('delivered', $message->status);
    }

    public function test_email_webhook_bounce(): void
    {
        $message = CrmMessage::factory()->email()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'external_id' => 'email-msg-id-002',
            'status' => 'sent',
        ]);

        $this->postJson('/api/v1/webhooks/email', [
            ['type' => 'bounced', 'message_id' => 'email-msg-id-002', 'reason' => 'Mailbox full'],
        ])->assertOk();

        $message->refresh();
        $this->assertEquals('failed', $message->status);
        $this->assertEquals('Mailbox full', $message->error_message);
    }

    // ─── Model Methods ──────────────────────────────────

    public function test_message_status_transitions(): void
    {
        $message = CrmMessage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
        ]);

        $message->markSent('ext-123');
        $this->assertEquals('sent', $message->fresh()->status);
        $this->assertEquals('ext-123', $message->fresh()->external_id);

        $message->markDelivered();
        $this->assertEquals('delivered', $message->fresh()->status);

        $message->markRead();
        $this->assertEquals('read', $message->fresh()->status);
    }

    public function test_message_mark_failed(): void
    {
        $message = CrmMessage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
        ]);

        $message->markFailed('Timeout');

        $msg = $message->fresh();
        $this->assertEquals('failed', $msg->status);
        $this->assertEquals('Timeout', $msg->error_message);
        $this->assertNotNull($msg->failed_at);
    }

    public function test_message_log_to_timeline(): void
    {
        $message = CrmMessage::factory()->whatsapp()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $activity = $message->logToTimeline();

        $this->assertDatabaseHas('crm_activities', [
            'id' => $activity->id,
            'customer_id' => $this->customer->id,
            'type' => 'whatsapp',
            'is_automated' => true,
        ]);
    }

    public function test_template_render(): void
    {
        $template = CrmMessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'body' => 'Olá {{nome}}, seu valor é R$ {{valor}}.',
            'subject' => 'Proposta para {{nome}}',
        ]);

        $rendered = $template->render([
            'nome' => 'Acme Corp',
            'valor' => '1.500,00',
        ]);

        $this->assertEquals('Olá Acme Corp, seu valor é R$ 1.500,00.', $rendered);

        $renderedSubject = $template->renderSubject(['nome' => 'Acme Corp']);
        $this->assertEquals('Proposta para Acme Corp', $renderedSubject);
    }

    // ─── Helper ─────────────────────────────────────────

    public function test_send_rejects_inactive_template_or_channel_mismatch(): void
    {
        $inactiveTemplate = CrmMessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'whatsapp',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/crm/messages/send', [
            'customer_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'body' => 'Teste',
            'template_id' => $inactiveTemplate->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['template_id']);

        $emailTemplate = CrmMessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'email',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/crm/messages/send', [
            'customer_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'body' => 'Teste',
            'template_id' => $emailTemplate->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['template_id']);
    }

    public function test_send_from_email_template_renders_default_subject_variables(): void
    {
        Mail::fake();

        $template = CrmMessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'email',
            'subject' => 'Proposta para {{nome}}',
            'body' => 'Ola {{nome}}, segue a proposta.',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/crm/messages/send', [
            'customer_id' => $this->customer->id,
            'channel' => 'email',
            'body' => 'ignorado quando usa template',
            'template_id' => $template->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subject', "Proposta para {$this->customer->name}");

        $this->assertDatabaseHas('crm_messages', [
            'id' => $response->json('data.id'),
            'subject' => "Proposta para {$this->customer->name}",
        ]);
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
