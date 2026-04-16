<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\StandardWeight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StandardWeightControllerTest extends TestCase
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

    private function createWeight(?int $tenantId = null): StandardWeight
    {
        return StandardWeight::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'code' => 'PW-'.uniqid(),
            'nominal_value' => 1000.00,
            'unit' => 'g',
            'precision_class' => 'F1',
            'status' => 'active',
        ]);
    }

    public function test_index_returns_only_current_tenant(): void
    {
        $mine = $this->createWeight();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createWeight($otherTenant->id);

        $response = $this->getJson('/api/v1/standard-weights');

        $response->assertOk();
        $rows = $response->json('data.data') ?? $response->json('data');
        $ids = collect($rows)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/standard-weights', []);

        $response->assertStatus(422);
    }

    public function test_expiring_returns_list(): void
    {
        $response = $this->getJson('/api/v1/standard-weights/expiring');

        $response->assertOk();
    }

    public function test_constants_returns_metadata(): void
    {
        $response = $this->getJson('/api/v1/standard-weights/constants');

        $response->assertOk();
    }

    public function test_show_returns_weight(): void
    {
        $weight = $this->createWeight();

        $response = $this->getJson("/api/v1/standard-weights/{$weight->id}");

        $response->assertOk();
    }
}
