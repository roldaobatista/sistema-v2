<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CollectionRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CollectionRuleController — regua de cobranca.
 *
 * Bug corrigido na Lei 2 (Testes Sao Sagrados): Model CollectionRule tinha
 * $fillable desalinhado do schema real:
 *  - Schema: (id, tenant_id, name, is_active, steps JSON, timestamps)
 *  - Fillable ANTES: ['days_offset', 'channel', 'template_type', ...] (colunas inexistentes)
 *  - Fillable DEPOIS: ['tenant_id', 'name', 'steps', 'is_active']
 *  - Cast: steps => array (JSON)
 *
 * Com o fix, o fluxo completo passa a funcionar: FormRequest valida steps[]
 * e o Model persiste como JSON.
 */
class CollectionRuleControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_rules(): void
    {
        CollectionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Regra atual tenant',
            'steps' => [['days_offset' => 3, 'channel' => 'email']],
            'is_active' => true,
        ]);

        $otherTenant = Tenant::factory()->create();
        CollectionRule::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Regra outro tenant',
            'steps' => [['days_offset' => 5, 'channel' => 'whatsapp']],
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/advanced/collection-rules');

        $response->assertOk();
        $body = $response->json();
        $data = $body['data'] ?? $body;
        $this->assertIsArray($data);

        foreach ($data as $rule) {
            if (isset($rule['tenant_id'])) {
                $this->assertEquals(
                    $this->tenant->id,
                    $rule['tenant_id'],
                    'CollectionRule cross-tenant vazou no index'
                );
            }
        }
    }

    public function test_store_validates_required_name_and_steps(): void
    {
        $response = $this->postJson('/api/v1/advanced/collection-rules', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'steps']);
    }

    public function test_store_rejects_invalid_channel(): void
    {
        $response = $this->postJson('/api/v1/advanced/collection-rules', [
            'name' => 'Regra Invalida',
            'steps' => [
                ['days_offset' => 3, 'channel' => 'carrier_pigeon'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['steps.0.channel']);
    }

    public function test_store_requires_days_offset_in_steps(): void
    {
        $response = $this->postJson('/api/v1/advanced/collection-rules', [
            'name' => 'Regra Sem Offset',
            'steps' => [
                ['channel' => 'email'], // sem days_offset
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['steps.0.days_offset']);
    }

    public function test_store_rejects_empty_steps_array(): void
    {
        $response = $this->postJson('/api/v1/advanced/collection-rules', [
            'name' => 'Regra Sem Steps',
            'steps' => [], // min:1
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['steps']);
    }

    public function test_store_creates_rule_with_valid_steps_array(): void
    {
        $response = $this->postJson('/api/v1/advanced/collection-rules', [
            'name' => 'Regra Lote6',
            'steps' => [
                ['days_offset' => 3, 'channel' => 'email', 'message_template' => 'Lembrete amigavel'],
                ['days_offset' => 7, 'channel' => 'whatsapp'],
                ['days_offset' => 15, 'channel' => 'sms'],
            ],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('collection_rules', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Regra Lote6',
        ]);

        // Valida que steps foi persistido como JSON com 3 entradas
        $rule = CollectionRule::where('name', 'Regra Lote6')->first();
        $this->assertNotNull($rule);
        $this->assertIsArray($rule->steps);
        $this->assertCount(3, $rule->steps);
        $this->assertSame('email', $rule->steps[0]['channel']);
    }
}
