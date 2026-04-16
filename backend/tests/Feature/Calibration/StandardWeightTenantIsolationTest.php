<?php

namespace Tests\Feature\Calibration;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\StandardWeight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StandardWeightTenantIsolationTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'is_active' => true,
        ]);
        app()->instance('current_tenant_id', $this->tenantA->id);
        Sanctum::actingAs($this->userA, ['*']);
    }

    public function test_standard_weight_from_other_tenant_invisible_in_list(): void
    {
        StandardWeight::factory()->create(['tenant_id' => $this->tenantA->id, 'code' => 'PP-A001']);
        StandardWeight::factory()->create(['tenant_id' => $this->tenantB->id, 'code' => 'PP-B001']);

        $response = $this->getJson('/api/v1/standard-weights');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code')->toArray();
        $this->assertContains('PP-A001', $codes);
        $this->assertNotContains('PP-B001', $codes);
    }

    public function test_cannot_access_other_tenant_standard_weight_by_id(): void
    {
        $otherWeight = StandardWeight::factory()->create(['tenant_id' => $this->tenantB->id]);

        // Direct access should 404 or return empty due to tenant scope
        $weight = StandardWeight::find($otherWeight->id);
        $this->assertNull($weight, 'Tenant scope should prevent accessing other tenant weight');
    }
}
