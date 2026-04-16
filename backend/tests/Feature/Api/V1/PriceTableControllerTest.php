<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\PriceTable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PriceTableControllerTest extends TestCase
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

    private function createTable(?int $tenantId = null, string $name = 'Padrão'): PriceTable
    {
        return PriceTable::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'multiplier' => 1.0,
            'is_active' => true,
        ]);
    }

    public function test_index_returns_only_current_tenant(): void
    {
        $mine = $this->createTable();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createTable($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/advanced/price-tables');

        $response->assertOk();
        $rows = $response->json('data.data') ?? $response->json('data');
        $ids = collect($rows)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/advanced/price-tables', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_price_table(): void
    {
        $response = $this->postJson('/api/v1/advanced/price-tables', [
            'name' => 'Tabela Premium',
            'multiplier' => 1.15,
            'is_active' => true,
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('price_tables', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Tabela Premium',
        ]);
    }

    public function test_show_returns_table(): void
    {
        $table = $this->createTable();

        $response = $this->getJson("/api/v1/advanced/price-tables/{$table->id}");

        $response->assertOk();
    }

    public function test_destroy_removes_table(): void
    {
        $table = $this->createTable();

        $response = $this->deleteJson("/api/v1/advanced/price-tables/{$table->id}");

        $this->assertContains($response->status(), [200, 204]);
    }
}
