<?php

namespace Tests\Feature\Api\V1\Crm;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmFieldManagementControllerTest extends TestCase
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

    public function test_constants_returns_metadata(): void
    {
        $response = $this->getJson('/api/v1/crm-field/constants');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_checkins_index_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/crm-field/checkins');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_checkin_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm-field/checkins', []);

        $response->assertStatus(422);
    }

    public function test_routes_index_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/crm-field/routes');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_routes_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm-field/routes', []);

        $response->assertStatus(422);
    }

    public function test_reports_index_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/crm-field/reports');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_policies_index_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/crm-field/policies');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_quick_notes_index_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/crm-field/quick-notes');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_quick_notes_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm-field/quick-notes', []);

        $response->assertStatus(422);
    }

    public function test_commitments_index_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/crm-field/commitments');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_smart_agenda_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/crm-field/smart-agenda');

        $response->assertOk();
    }

    public function test_rfm_index_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/crm-field/rfm');

        $response->assertOk()->assertJsonStructure(['data']);
    }
}
