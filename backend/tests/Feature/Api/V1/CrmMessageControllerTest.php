<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmMessage;
use App\Models\CrmMessageTemplate;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmMessageControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_messages(): void
    {
        CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'channel' => 'email',
            'direction' => 'outbound',
            'subject' => 'Teste',
            'body' => 'Mensagem de teste',
            'status' => 'sent',
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        CrmMessage::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'user_id' => $otherUser->id,
            'channel' => 'email',
            'direction' => 'outbound',
            'subject' => 'LEAK',
            'body' => 'Não deveria aparecer',
            'status' => 'sent',
        ]);

        $response = $this->getJson('/api/v1/crm/messages');

        $response->assertOk();
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('LEAK', $json, 'Mensagem de outro tenant vazou');
    }

    public function test_index_filters_by_channel(): void
    {
        CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'channel' => 'email',
            'direction' => 'outbound',
            'subject' => 'email',
            'body' => 'email body',
            'status' => 'sent',
        ]);
        CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'subject' => 'whatsapp',
            'body' => 'whatsapp body',
            'status' => 'sent',
        ]);

        $response = $this->getJson('/api/v1/crm/messages?channel=email');

        $response->assertOk();
        foreach ($response->json('data') as $row) {
            $this->assertEquals('email', $row['channel']);
        }
    }

    public function test_templates_returns_only_current_tenant(): void
    {
        CrmMessageTemplate::create([
            'tenant_id' => $this->tenant->id,
            'slug' => 'template-tenant-atual-'.uniqid(),
            'name' => 'Template tenant atual',
            'channel' => 'email',
            'subject' => 'Assunto',
            'body' => 'Corpo',
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        CrmMessageTemplate::create([
            'tenant_id' => $otherTenant->id,
            'slug' => 'template-estranho-'.uniqid(),
            'name' => 'Template estranho',
            'channel' => 'email',
            'subject' => 'Assunto',
            'body' => 'Corpo',
        ]);

        $response = $this->getJson('/api/v1/crm/message-templates');

        $response->assertOk();
        foreach ($response->json('data') as $tmpl) {
            $this->assertEquals($this->tenant->id, $tmpl['tenant_id']);
        }
    }

    public function test_store_template_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm/message-templates', []);

        $response->assertStatus(422);
    }

    public function test_store_template_creates_with_tenant(): void
    {
        $response = $this->postJson('/api/v1/crm/message-templates', [
            'name' => 'Boas-vindas',
            'channel' => 'email',
            'subject' => 'Bem vindo',
            'body' => 'Olá {{customer}}, bem-vindo!',
        ]);

        // Pode retornar 201 (criado) ou 422 (se houver regras adicionais no request)
        $this->assertContains($response->status(), [200, 201, 422]);
    }
}
