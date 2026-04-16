<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeaturesControllerTest extends TestCase
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

    public function test_list_calibrations_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/calibration');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_calculate_ema_returns_results(): void
    {
        $response = $this->postJson('/api/v1/calibration/calculate-ema', [
            'precision_class' => 'III',
            'e_value' => 1.0,
            'loads' => [100, 500, 1000],
            'verification_type' => 'initial',
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['ema_results']]);
    }

    public function test_calculate_ema_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/calibration/calculate-ema', []);

        $response->assertStatus(422);
    }

    public function test_list_calibrations_returns_pagination_structure(): void
    {
        $response = $this->getJson('/api/v1/calibration');

        $response->assertOk()->assertJsonStructure(['data']);
    }
}
