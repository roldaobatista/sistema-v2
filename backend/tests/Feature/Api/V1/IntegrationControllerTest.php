<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntegrationControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
    }

    // ─── WEBHOOKS ─────────────────────────────────────────────

    public function test_list_webhooks_returns_data(): void
    {
        // Webhooks do tenant atual (devem aparecer)
        DB::table('webhooks')->insert([
            [
                'tenant_id' => $this->tenant->id,
                'name' => 'Hook A',
                'url' => 'https://example.com/hook-a',
                'event' => 'work_order.created',
                'events' => json_encode(['work_order.created']),
                'secret' => 'secret-a',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->tenant->id,
                'name' => 'Hook B',
                'url' => 'https://example.com/hook-b',
                'event' => 'work_order.completed',
                'events' => json_encode(['work_order.completed']),
                'secret' => 'secret-b',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Webhook de OUTRO tenant (não pode vazar)
        $otherTenant = Tenant::factory()->create();
        DB::table('webhooks')->insert([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Hook',
            'url' => 'https://other.example.com/hook',
            'event' => 'work_order.created',
            'events' => json_encode(['work_order.created']),
            'secret' => 'other-secret',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/integrations/webhooks');

        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Hook A', 'Hook B'], $names, 'Listagem deve retornar apenas webhooks do tenant atual, ordenados por nome');

        // Garante não vazamento entre tenants
        $this->assertNotContains('Other Tenant Hook', $names);
    }

    public function test_store_webhook_creates_entry(): void
    {
        $response = $this->postJson('/api/v1/integrations/webhooks', [
            'url' => 'https://example.com/new-hook',
            'event' => 'os.completed',
        ]);

        $response->assertCreated();

        $webhook = DB::table('webhooks')
            ->where('tenant_id', $this->tenant->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($webhook);
        $this->assertSame('os.completed', $webhook->event);
        $this->assertSame(['work_order.completed'], json_decode($webhook->events, true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_store_webhook_validates_event(): void
    {
        $response = $this->postJson('/api/v1/integrations/webhooks', [
            'url' => 'https://example.com/new-hook',
            'event' => 'invalid.event',
        ]);

        $response->assertStatus(422);
    }

    public function test_delete_webhook_removes_entry(): void
    {
        $id = DB::table('webhooks')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Delete me',
            'url' => 'https://example.com/delete-me',
            'event' => 'work_order.created',
            'events' => json_encode(['work_order.created']),
            'secret' => 'secret',
            'is_active' => true,
            'failure_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/integrations/webhooks/{$id}");

        $response->assertOk();

        // Garante remoção efetiva no DB
        $this->assertDatabaseMissing('webhooks', [
            'id' => $id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_delete_webhook_does_not_affect_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherId = DB::table('webhooks')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Hook',
            'url' => 'https://other.example.com/hook',
            'event' => 'work_order.created',
            'events' => json_encode(['work_order.created']),
            'secret' => 'other-secret',
            'is_active' => true,
            'failure_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tentativa cross-tenant de deletar o webhook
        $response = $this->deleteJson("/api/v1/integrations/webhooks/{$otherId}");

        // Controller retorna 200 no "sucesso" mesmo se nada for deletado (idempotente)
        $response->assertOk();

        // Mas o webhook do outro tenant DEVE continuar intacto
        $this->assertDatabaseHas('webhooks', [
            'id' => $otherId,
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Hook',
        ]);
    }

    // ─── MARKETPLACE ──────────────────────────────────────────

    public function test_marketplace_returns_data(): void
    {
        $response = $this->getJson('/api/v1/integrations/marketplace');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─── SSO CONFIG ───────────────────────────────────────────

    public function test_sso_config_returns_data(): void
    {
        $response = $this->getJson('/api/v1/integrations/sso-config');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─── SHIPPING CALCULATOR ──────────────────────────────────

    public function test_calculate_shipping_returns_quotes(): void
    {
        $response = $this->postJson('/api/v1/integrations/shipping/calculate', [
            'origin_zip' => '01001-000',
            'destination_zip' => '20040-020',
            'weight_kg' => 5.0,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $this->assertIsArray($response->json('data'));
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_calculate_shipping_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/integrations/shipping/calculate', []);

        $response->assertStatus(422);
    }

    // ─── SWAGGER DOC ──────────────────────────────────────────

    public function test_swagger_doc_returns_openapi_spec(): void
    {
        $response = $this->getJson('/api/v1/integrations/swagger');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['openapi', 'info', 'paths'],
            ]);
    }

    // ─── EMAIL PLUGIN ─────────────────────────────────────────

    public function test_email_plugin_manifest_returns_capabilities(): void
    {
        $response = $this->getJson('/api/v1/integrations/email-plugin/manifest');

        $response->assertOk()
            ->assertJsonPath('data.name', 'Kalibrium ERP Mail Plugin')
            ->assertJsonStructure([
                'data' => ['name', 'version', 'capabilities', 'supported_providers'],
            ]);
    }

    // ─── MARKETING CONFIG ─────────────────────────────────────

    public function test_marketing_config_returns_data(): void
    {
        $response = $this->getJson('/api/v1/integrations/marketing-config');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─── SLACK TEAMS CONFIG ───────────────────────────────────

    public function test_slack_teams_config_returns_data(): void
    {
        $response = $this->getJson('/api/v1/integrations/slack-teams-config');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
