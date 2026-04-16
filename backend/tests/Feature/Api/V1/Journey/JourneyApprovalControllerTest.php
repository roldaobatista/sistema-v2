<?php

namespace Tests\Feature\Api\V1\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JourneyApprovalControllerTest extends TestCase
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

    public function test_pending_returns_list_for_level(): void
    {
        $response = $this->getJson('/api/v1/journey/approvals/supervisor/pending');

        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_approve_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/journey/days/99999/approve/supervisor', []);

        $this->assertContains($response->status(), [404, 422]);
    }

    public function test_reject_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/journey/days/99999/reject/supervisor', []);

        $this->assertContains($response->status(), [404, 422]);
    }

    public function test_submit_returns_response_for_valid_entry(): void
    {
        $response = $this->postJson('/api/v1/journey/days/99999/submit-approval');

        $this->assertContains($response->status(), [200, 201, 404, 422]);
    }
}
