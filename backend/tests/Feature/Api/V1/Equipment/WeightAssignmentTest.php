<?php

namespace Tests\Feature\Api\V1\Equipment;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\StandardWeight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WeightAssignmentTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private int $standardWeightId;

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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->standardWeightId = StandardWeight::factory()
            ->active()
            ->create([
                'tenant_id' => $this->tenant->id,
                'code' => 'SW-TEST-001',
                'nominal_value' => 1000,
                'unit' => 'g',
                'precision_class' => 'F1',
            ])->id;
    }

    public function test_index_returns_paginated_assignments(): void
    {
        DB::table('weight_assignments')->insert([
            'standard_weight_id' => $this->standardWeightId,
            'assigned_to_user_id' => $this->user->id,
            'assignment_type' => 'user',
            'assigned_at' => now(),
            'tenant_id' => $this->tenant->id,
            'assigned_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/weight-assignments');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_index_filters_by_user_id(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        DB::table('weight_assignments')->insert([
            'standard_weight_id' => $this->standardWeightId,
            'assigned_to_user_id' => $this->user->id,
            'assignment_type' => 'user',
            'assigned_at' => now(),
            'tenant_id' => $this->tenant->id,
            'assigned_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('weight_assignments')->insert([
            'standard_weight_id' => $this->standardWeightId,
            'assigned_to_user_id' => $otherUser->id,
            'assignment_type' => 'user',
            'assigned_at' => now(),
            'tenant_id' => $this->tenant->id,
            'assigned_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/weight-assignments?user_id={$this->user->id}");

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        foreach ($items as $item) {
            $this->assertEquals($this->user->id, $item['assigned_to_user_id']);
        }
    }

    public function test_store_creates_assignment_successfully(): void
    {
        $payload = [
            'standard_weight_id' => $this->standardWeightId,
            'user_id' => $this->user->id,
            'notes' => 'Atribuição para calibração externa',
        ];

        $response = $this->postJson('/api/v1/weight-assignments', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('weight_assignments', [
            'standard_weight_id' => $this->standardWeightId,
            'assigned_to_user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_fails_without_required_fields(): void
    {
        $response = $this->postJson('/api/v1/weight-assignments', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['standard_weight_id', 'user_id']);
    }

    public function test_store_fails_with_invalid_standard_weight(): void
    {
        $payload = [
            'standard_weight_id' => 999999,
            'user_id' => $this->user->id,
        ];

        $response = $this->postJson('/api/v1/weight-assignments', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('standard_weight_id');
    }

    public function test_store_with_assigned_at_date(): void
    {
        $payload = [
            'standard_weight_id' => $this->standardWeightId,
            'user_id' => $this->user->id,
            'assigned_at' => '2026-03-01',
        ];

        $response = $this->postJson('/api/v1/weight-assignments', $payload);

        $response->assertStatus(201);
    }

    public function test_update_modifies_assignment(): void
    {
        $id = DB::table('weight_assignments')->insertGetId([
            'standard_weight_id' => $this->standardWeightId,
            'assigned_to_user_id' => $this->user->id,
            'assignment_type' => 'user',
            'assigned_at' => now(),
            'tenant_id' => $this->tenant->id,
            'assigned_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/weight-assignments/{$id}", [
            'status' => 'returned',
            'returned_at' => '2026-03-09',
            'notes' => 'Devolvido em bom estado',
        ]);

        $response->assertOk();
    }

    public function test_update_returns_404_for_nonexistent(): void
    {
        $response = $this->putJson('/api/v1/weight-assignments/999999', [
            'notes' => 'test',
        ]);

        $response->assertStatus(404);
    }

    public function test_destroy_removes_assignment(): void
    {
        $id = DB::table('weight_assignments')->insertGetId([
            'standard_weight_id' => $this->standardWeightId,
            'assigned_to_user_id' => $this->user->id,
            'assignment_type' => 'user',
            'assigned_at' => now(),
            'tenant_id' => $this->tenant->id,
            'assigned_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/weight-assignments/{$id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('weight_assignments', ['id' => $id]);
    }

    public function test_destroy_returns_404_for_nonexistent(): void
    {
        $response = $this->deleteJson('/api/v1/weight-assignments/999999');

        $response->assertStatus(404);
    }

    public function test_tenant_isolation_prevents_cross_tenant_delete(): void
    {
        $otherTenant = Tenant::factory()->create();
        $id = DB::table('weight_assignments')->insertGetId([
            'standard_weight_id' => $this->standardWeightId,
            'assigned_to_user_id' => $this->user->id,
            'assignment_type' => 'user',
            'assigned_at' => now(),
            'tenant_id' => $otherTenant->id,
            'assigned_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/weight-assignments/{$id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('weight_assignments', ['id' => $id]);
    }

    public function test_tenant_isolation_prevents_cross_tenant_update(): void
    {
        $otherTenant = Tenant::factory()->create();
        $id = DB::table('weight_assignments')->insertGetId([
            'standard_weight_id' => $this->standardWeightId,
            'assigned_to_user_id' => $this->user->id,
            'assignment_type' => 'user',
            'assigned_at' => now(),
            'tenant_id' => $otherTenant->id,
            'assigned_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/weight-assignments/{$id}", [
            'notes' => 'Cross-tenant hack',
        ]);

        $response->assertStatus(404);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/weight-assignments');

        $response->assertUnauthorized();
    }
}
