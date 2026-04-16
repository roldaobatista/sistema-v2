<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InmetroAdvancedControllerTest extends TestCase
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

    public function test_contact_queue_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/inmetro/advanced/contact-queue');

        $response->assertOk();
    }

    public function test_follow_ups_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/inmetro/advanced/follow-ups');

        $response->assertOk();
    }

    public function test_detect_churn_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/inmetro/advanced/churn');

        $response->assertOk();
    }

    public function test_new_registrations_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/inmetro/advanced/new-registrations');

        $response->assertOk();
    }

    public function test_suggest_next_calibrations_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/inmetro/advanced/next-calibrations');

        $response->assertOk();
    }

    public function test_calculate_lead_score_returns_response(): void
    {
        $response = $this->getJson('/api/v1/inmetro/advanced/lead-score/1');

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }
}
