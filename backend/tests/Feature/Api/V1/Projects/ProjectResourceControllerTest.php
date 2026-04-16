<?php

namespace Tests\Feature\Api\V1\Projects;

require_once __DIR__.'/ProjectsFeatureTestCase.php';

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProjectResourceControllerTest extends ProjectsFeatureTestCase
{
    private function createProject(): Project
    {
        return Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'created_by' => $this->user->id,
            'status' => 'active',
        ]);
    }

    public function test_can_list_resources_with_pagination(): void
    {
        $this->actingAsUser();
        $project = $this->createProject();

        foreach (range(1, 12) as $index) {
            DB::table('project_resources')->insert([
                'tenant_id' => $this->tenant->id,
                'project_id' => $project->id,
                'user_id' => User::factory()->create([
                    'tenant_id' => $this->tenant->id,
                    'current_tenant_id' => $this->tenant->id,
                ])->id,
                'role' => "Role {$index}",
                'allocation_percent' => 25,
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'hourly_rate' => 120,
                'total_hours_planned' => 80,
                'total_hours_logged' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->getJson("/api/v1/projects/{$project->id}/resources?per_page=10")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'user_id',
                    'role',
                    'allocation_percent',
                ]],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_can_create_project_resource(): void
    {
        $this->actingAsUser();
        $project = $this->createProject();
        $resourceUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->postJson("/api/v1/projects/{$project->id}/resources", [
            'user_id' => $resourceUser->id,
            'role' => 'Consultor',
            'allocation_percent' => 50,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'hourly_rate' => 180,
            'total_hours_planned' => 120,
        ])->assertCreated()
            ->assertJsonPath('data.role', 'Consultor');

        $this->assertDatabaseHas('project_resources', [
            'project_id' => $project->id,
            'user_id' => $resourceUser->id,
            'role' => 'Consultor',
        ]);
    }

    public function test_store_validates_resource_payload(): void
    {
        $this->actingAsUser();
        $project = $this->createProject();

        $this->postJson("/api/v1/projects/{$project->id}/resources", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'role', 'allocation_percent', 'start_date', 'end_date']);
    }

    public function test_can_update_project_resource(): void
    {
        $this->actingAsUser();
        $project = $this->createProject();
        $resourceUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $resourceId = DB::table('project_resources')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'user_id' => $resourceUser->id,
            'role' => 'Analista',
            'allocation_percent' => 25,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'hourly_rate' => 100,
            'total_hours_planned' => 80,
            'total_hours_logged' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->putJson("/api/v1/projects/{$project->id}/resources/{$resourceId}", [
            'role' => 'Lider Tecnico',
            'allocation_percent' => 75,
            'start_date' => '2026-04-01',
            'end_date' => '2026-05-15',
        ])->assertOk()
            ->assertJsonPath('data.role', 'Lider Tecnico');

        $this->assertDatabaseHas('project_resources', [
            'id' => $resourceId,
            'role' => 'Lider Tecnico',
            'allocation_percent' => '75.00',
        ]);
    }

    public function test_can_delete_project_resource(): void
    {
        $this->actingAsUser();
        $project = $this->createProject();
        $resourceUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $resourceId = DB::table('project_resources')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'user_id' => $resourceUser->id,
            'role' => 'Remover',
            'allocation_percent' => 25,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'hourly_rate' => 100,
            'total_hours_planned' => 80,
            'total_hours_logged' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson("/api/v1/projects/{$project->id}/resources/{$resourceId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('project_resources', ['id' => $resourceId]);
    }

    public function test_project_resources_respect_tenant_isolation(): void
    {
        $this->actingAsUser();

        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $otherTenant->id])->id,
        ]);

        $resourceId = DB::table('project_resources')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'user_id' => User::factory()->create([
                'tenant_id' => $otherTenant->id,
                'current_tenant_id' => $otherTenant->id,
            ])->id,
            'role' => 'Outro tenant',
            'allocation_percent' => 25,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'hourly_rate' => 100,
            'total_hours_planned' => 80,
            'total_hours_logged' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/projects/{$otherProject->id}/resources")->assertNotFound();
        $this->putJson("/api/v1/projects/{$otherProject->id}/resources/{$resourceId}", [
            'role' => 'Inválido',
            'allocation_percent' => 15,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ])->assertNotFound();
    }

    public function test_requires_authentication_and_permissions_for_resources(): void
    {
        $project = $this->createProject();

        $this->getJson("/api/v1/projects/{$project->id}/resources")->assertUnauthorized();

        Gate::before(fn () => false);
        $this->actingAsUser();
        $this->withMiddleware([CheckPermission::class]);

        $response = $this->getJson("/api/v1/projects/{$project->id}/resources");
        $this->assertContains($response->status(), [403, 404]);
    }
}
