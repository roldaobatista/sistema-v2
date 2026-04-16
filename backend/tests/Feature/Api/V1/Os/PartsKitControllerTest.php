<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\PartsKit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartsKitControllerTest extends TestCase
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

    private function createKit(?int $tenantId = null, string $name = 'Kit Padrão'): PartsKit
    {
        return PartsKit::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'description' => null,
            'is_active' => true,
        ]);
    }

    public function test_index_returns_only_current_tenant_kits(): void
    {
        $mine = $this->createKit();
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createKit($otherTenant->id, 'Kit estranho');

        $response = $this->getJson('/api/v1/parts-kits');

        $response->assertOk()->assertJsonStructure(['data']);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids, 'Kit do tenant atual deveria aparecer');
        $this->assertNotContains($foreign->id, $ids, 'Kit de outro tenant vazou na listagem');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/parts-kits', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'items']);
    }

    public function test_store_rejects_empty_items_array(): void
    {
        $response = $this->postJson('/api/v1/parts-kits', [
            'name' => 'Vazio',
            'items' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_store_rejects_invalid_item_type(): void
    {
        $response = $this->postJson('/api/v1/parts-kits', [
            'name' => 'Kit com tipo inválido',
            'items' => [[
                'type' => 'nonsense',
                'description' => 'Item X',
                'quantity' => 1,
                'unit_price' => 10,
            ]],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.type']);
    }

    public function test_store_creates_kit_with_items(): void
    {
        $response = $this->postJson('/api/v1/parts-kits', [
            'name' => 'Kit Manutenção',
            'description' => 'Kit básico de manutenção',
            'items' => [
                [
                    'type' => 'service',
                    'description' => 'Serviço técnico',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
                [
                    'type' => 'product',
                    'description' => 'Peça X',
                    'quantity' => 2,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('parts_kits', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Kit Manutenção',
        ]);
        // Kit criado tem 2 itens
        $kit = PartsKit::where('tenant_id', $this->tenant->id)->where('name', 'Kit Manutenção')->first();
        $this->assertNotNull($kit);
        $this->assertSame(2, $kit->items()->count());
    }

    public function test_show_returns_404_for_cross_tenant_kit(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createKit($otherTenant->id, 'Foreign Kit');

        $response = $this->getJson("/api/v1/parts-kits/{$foreign->id}");

        $response->assertStatus(404);
    }
}
