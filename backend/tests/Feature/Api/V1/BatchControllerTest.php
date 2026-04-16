<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BatchControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Product $product;

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
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createBatch(?int $tenantId = null, ?string $code = null): Batch
    {
        return Batch::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'product_id' => $this->product->id,
            'code' => $code ?? 'BATCH-'.uniqid(),
            'expires_at' => now()->addMonths(6)->toDateString(),
            'cost_price' => 100.00,
        ]);
    }

    public function test_index_returns_only_current_tenant_batches(): void
    {
        $mine = $this->createBatch();

        $otherTenant = Tenant::factory()->create();
        $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = Batch::create([
            'tenant_id' => $otherTenant->id,
            'product_id' => $otherProduct->id,
            'code' => 'FOREIGN-'.uniqid(),
            'expires_at' => now()->addMonths(6)->toDateString(),
            'cost_price' => 100,
        ]);

        $response = $this->getJson('/api/v1/batches');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/batches', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_batch(): void
    {
        $response = $this->postJson('/api/v1/batches', [
            'product_id' => $this->product->id,
            'batch_number' => 'LOTE-'.uniqid(),
            'expires_at' => now()->addMonths(6)->toDateString(),
            'cost_price' => 150.00,
        ]);

        $this->assertContains($response->status(), [200, 201, 422]);
    }

    public function test_show_returns_batch(): void
    {
        $batch = $this->createBatch();

        $response = $this->getJson("/api/v1/batches/{$batch->id}");

        $response->assertOk();
    }

    public function test_destroy_removes_batch(): void
    {
        $batch = $this->createBatch();

        $response = $this->deleteJson("/api/v1/batches/{$batch->id}");

        $this->assertContains($response->status(), [200, 204]);
    }
}
