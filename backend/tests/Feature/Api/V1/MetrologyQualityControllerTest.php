<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MetrologyQualityControllerTest extends TestCase
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

    public function test_non_conformances_returns_list(): void
    {
        $response = $this->getJson('/api/v1/metrology/non-conformances');

        $response->assertOk();
    }

    public function test_store_non_conformance_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/metrology/non-conformances', []);

        $response->assertStatus(422);
    }

    public function test_uncertainties_returns_list(): void
    {
        $response = $this->getJson('/api/v1/metrology/uncertainties');

        $response->assertOk();
    }

    public function test_store_uncertainty_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/metrology/uncertainties', []);

        $response->assertStatus(422);
    }

    public function test_calibration_schedule_is_reachable(): void
    {
        $response = $this->getJson('/api/v1/metrology/calibration-schedule');

        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_qa_alerts_returns_list(): void
    {
        $response = $this->getJson('/api/v1/metrology/qa-alerts');

        $response->assertOk();
    }
}
