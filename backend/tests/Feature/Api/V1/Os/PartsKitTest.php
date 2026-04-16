<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\PartsKit;
use App\Models\PartsKitItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartsKitTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

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
        $this->otherTenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createKit(array $overrides = [], int $itemCount = 2): PartsKit
    {
        $kit = PartsKit::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Kit de Teste',
            'description' => 'Descrição do kit',
            'is_active' => true,
        ], $overrides));

        for ($i = 0; $i < $itemCount; $i++) {
            PartsKitItem::create([
                'parts_kit_id' => $kit->id,
                'type' => 'product',
                'description' => "Item {$i}",
                'quantity' => 2,
                'unit_price' => 50.00,
            ]);
        }

        return $kit;
    }

    // ── INDEX ──

    public function test_index_lists_kits_with_items_count(): void
    {
        $this->createKit(['name' => 'Kit A'], 3);
        $this->createKit(['name' => 'Kit B'], 1);

        $response = $this->getJson('/api/v1/parts-kits');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_index_search_filter(): void
    {
        $this->createKit(['name' => 'Kit Manutenção']);
        $this->createKit(['name' => 'Kit Instalação']);

        $response = $this->getJson('/api/v1/parts-kits?search=Manutenção');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_index_active_filter(): void
    {
        $this->createKit(['name' => 'Kit Ativo', 'is_active' => true]);
        $this->createKit(['name' => 'Kit Inativo', 'is_active' => false]);

        $response = $this->getJson('/api/v1/parts-kits?is_active=1');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_index_tenant_isolation(): void
    {
        $this->createKit(['name' => 'Meu kit']);
        PartsKit::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Kit de outro tenant',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/parts-kits');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    // ── SHOW ──

    public function test_show_returns_kit_with_items_and_total(): void
    {
        $kit = $this->createKit(['name' => 'Kit Completo'], 2);

        $response = $this->getJson("/api/v1/parts-kits/{$kit->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
        // 2 items * 2 qty * 50.00 = 200.00
        $this->assertEquals('200.00', $response->json('data.total'));
    }

    public function test_show_returns_404_for_other_tenant_kit(): void
    {
        $otherKit = PartsKit::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Kit Alheio',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/parts-kits/{$otherKit->id}");

        $response->assertStatus(404);
    }

    // ── STORE ──

    public function test_store_creates_kit_with_items(): void
    {
        $response = $this->postJson('/api/v1/parts-kits', [
            'name' => 'Novo Kit',
            'description' => 'Descrição',
            'is_active' => true,
            'items' => [
                [
                    'type' => 'product',
                    'description' => 'Peça A',
                    'quantity' => 3,
                    'unit_price' => 25.50,
                ],
                [
                    'type' => 'service',
                    'description' => 'Mão de obra',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('parts_kits', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Novo Kit',
        ]);
        $this->assertDatabaseHas('parts_kit_items', [
            'description' => 'Peça A',
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('parts_kit_items', [
            'description' => 'Mão de obra',
        ]);
    }

    public function test_store_validates_name_required(): void
    {
        $response = $this->postJson('/api/v1/parts-kits', [
            'items' => [
                ['type' => 'product', 'description' => 'X', 'quantity' => 1, 'unit_price' => 10],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_store_validates_items_required(): void
    {
        $response = $this->postJson('/api/v1/parts-kits', [
            'name' => 'Kit sem items',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_validates_item_type(): void
    {
        $response = $this->postJson('/api/v1/parts-kits', [
            'name' => 'Kit',
            'items' => [
                ['type' => 'invalid', 'description' => 'X', 'quantity' => 1, 'unit_price' => 10],
            ],
        ]);

        $response->assertStatus(422);
    }

    // ── UPDATE ──

    public function test_update_kit_name_and_items(): void
    {
        $kit = $this->createKit(['name' => 'Kit Original'], 1);

        $response = $this->putJson("/api/v1/parts-kits/{$kit->id}", [
            'name' => 'Kit Atualizado',
            'items' => [
                ['type' => 'service', 'description' => 'Serviço novo', 'quantity' => 1, 'unit_price' => 200],
                ['type' => 'product', 'description' => 'Peça nova', 'quantity' => 5, 'unit_price' => 10],
            ],
        ]);

        $response->assertOk();

        $kit->refresh();
        $this->assertEquals('Kit Atualizado', $kit->name);
        $this->assertCount(2, $kit->items);
    }

    public function test_update_replaces_items_completely(): void
    {
        $kit = $this->createKit([], 3);
        $this->assertCount(3, $kit->items);

        $response = $this->putJson("/api/v1/parts-kits/{$kit->id}", [
            'items' => [
                ['type' => 'product', 'description' => 'Unico item', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(1, $kit->fresh()->items);
    }

    public function test_update_returns_404_for_other_tenant(): void
    {
        $otherKit = PartsKit::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Kit Alheio',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/parts-kits/{$otherKit->id}", [
            'name' => 'Tentando alterar',
        ]);

        $response->assertStatus(404);
    }

    // ── DESTROY ──

    public function test_destroy_deletes_kit_and_items(): void
    {
        $kit = $this->createKit([], 2);
        $kitId = $kit->id;

        $response = $this->deleteJson("/api/v1/parts-kits/{$kitId}");

        $response->assertOk();
        $this->assertSoftDeleted('parts_kits', ['id' => $kitId]);
    }

    public function test_destroy_returns_404_for_other_tenant(): void
    {
        $otherKit = PartsKit::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Kit Alheio',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/parts-kits/{$otherKit->id}");

        $response->assertStatus(404);
    }

    // ── APPLY TO WORK ORDER ──

    public function test_apply_kit_to_work_order(): void
    {
        $kit = $this->createKit(['name' => 'Kit Aplicavel'], 2);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/apply-kit/{$kit->id}");

        $response->assertOk();

        $wo->refresh();
        $this->assertCount(2, $wo->items);

        // Each item: qty=2 * price=50 = 100
        foreach ($wo->items as $item) {
            $this->assertEquals('100.00', $item->total);
        }
    }

    public function test_apply_kit_tenant_isolation(): void
    {
        $otherKit = PartsKit::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Kit Alheio',
            'is_active' => true,
        ]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/apply-kit/{$otherKit->id}");

        $response->assertStatus(404);
    }
}
