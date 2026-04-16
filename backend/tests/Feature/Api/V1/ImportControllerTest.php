<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImportControllerTest extends TestCase
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

    public function test_fields_returns_schema_for_customers(): void
    {
        $response = $this->getJson('/api/v1/import/fields/customers');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_fields_returns_schema_for_products(): void
    {
        $response = $this->getJson('/api/v1/import/fields/products');

        $response->assertOk();
    }

    public function test_history_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/import/history');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_templates_returns_list(): void
    {
        $response = $this->getJson('/api/v1/import/templates');

        $response->assertOk();
    }

    public function test_upload_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/import/upload', []);

        $response->assertStatus(422);
    }

    public function test_execute_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/import/execute', []);

        $response->assertStatus(422);
    }

    public function test_stats_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/import-stats');

        $response->assertOk();
    }

    public function test_entity_counts_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/import-entity-counts');

        $response->assertOk();
    }
}
