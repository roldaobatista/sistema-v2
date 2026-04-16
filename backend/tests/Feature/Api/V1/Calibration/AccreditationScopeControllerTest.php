<?php

namespace Tests\Feature\Api\V1\Calibration;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccreditationScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccreditationScopeControllerTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

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
        $this->otherTenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'accreditation_number' => 'CRL-0042',
            'accrediting_body' => 'Cgcre/Inmetro',
            'scope_description' => 'Calibração de instrumentos de pesagem não automáticos',
            'equipment_categories' => ['Balancas Comerciais', 'Balancas Industriais'],
            'valid_from' => '2025-01-01',
            'valid_until' => '2027-01-01',
        ], $overrides);
    }

    // ─── CRUD ─────────────────────────────────────────────��──────

    public function test_store_creates_accreditation_scope(): void
    {
        $response = $this->postJson('/api/v1/accreditation-scopes', $this->validPayload());

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => [
            'id', 'accreditation_number', 'accrediting_body', 'scope_description',
            'equipment_categories', 'valid_from', 'valid_until', 'is_active', 'is_valid', 'is_expired',
        ]]);
        $this->assertDatabaseHas('accreditation_scopes', [
            'accreditation_number' => 'CRL-0042',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_index_lists_scopes_paginated(): void
    {
        AccreditationScope::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        AccreditationScope::factory()->create(['tenant_id' => $this->otherTenant->id]);

        $response = $this->getJson('/api/v1/accreditation-scopes');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_show_returns_scope(): void
    {
        $scope = AccreditationScope::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson("/api/v1/accreditation-scopes/{$scope->id}");

        $response->assertOk();
        $response->assertJsonPath('data.accreditation_number', $scope->accreditation_number);
    }

    public function test_update_modifies_scope(): void
    {
        $scope = AccreditationScope::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->putJson("/api/v1/accreditation-scopes/{$scope->id}", [
            'accreditation_number' => 'CRL-9999',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('accreditation_scopes', [
            'id' => $scope->id,
            'accreditation_number' => 'CRL-9999',
        ]);
    }

    public function test_destroy_deletes_scope(): void
    {
        $scope = AccreditationScope::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->deleteJson("/api/v1/accreditation-scopes/{$scope->id}")
            ->assertOk();

        $this->assertDatabaseMissing('accreditation_scopes', ['id' => $scope->id]);
    }

    // ─── Filtering ───────────────────────────────────────────────

    public function test_for_category_filters_by_json_contains(): void
    {
        AccreditationScope::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_categories' => ['Balancas Comerciais'],
        ]);
        AccreditationScope::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_categories' => ['Paquimetros'],
        ]);

        $results = AccreditationScope::where('tenant_id', $this->tenant->id)
            ->forCategory('Balancas Comerciais')
            ->get();

        $this->assertCount(1, $results);
    }

    public function test_expired_scope_not_in_active(): void
    {
        AccreditationScope::factory()->expired()->create(['tenant_id' => $this->tenant->id]);
        AccreditationScope::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/accreditation-scopes-active');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_inactive_scope_not_in_active(): void
    {
        AccreditationScope::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/accreditation-scopes-active');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    // ─── Security ────────────────────────────────────────────────

    public function test_cross_tenant_returns_404(): void
    {
        $otherScope = AccreditationScope::factory()->create(['tenant_id' => $this->otherTenant->id]);

        $this->getJson("/api/v1/accreditation-scopes/{$otherScope->id}")
            ->assertStatus(404);
    }

    public function test_permission_403_without_accreditation_permission(): void
    {
        $this->withMiddleware(CheckPermission::class);

        $limitedUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        Sanctum::actingAs($limitedUser, ['*']);

        $this->getJson('/api/v1/accreditation-scopes')
            ->assertStatus(403);
    }

    public function test_validation_422_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/accreditation-scopes', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'accreditation_number', 'scope_description',
            'equipment_categories', 'valid_from', 'valid_until',
        ]);
    }

    public function test_validation_422_valid_until_must_be_after_valid_from(): void
    {
        $response = $this->postJson('/api/v1/accreditation-scopes', $this->validPayload([
            'valid_from' => '2027-01-01',
            'valid_until' => '2025-01-01',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['valid_until']);
    }
}
