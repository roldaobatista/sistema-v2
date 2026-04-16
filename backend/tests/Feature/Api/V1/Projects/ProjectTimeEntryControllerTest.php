<?php

namespace Tests\Feature\Api\V1\Projects;

require_once __DIR__.'/ProjectsFeatureTestCase.php';

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProjectTimeEntryControllerTest extends ProjectsFeatureTestCase
{
    private function createProject(): Project
    {
        return Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'created_by' => $this->user->id,
            'status' => 'active',
            'spent' => 0,
        ]);
    }

    private function createResource(Project $project, float $hourlyRate = 100): int
    {
        return DB::table('project_resources')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'user_id' => User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'current_tenant_id' => $this->tenant->id,
            ])->id,
            'role' => 'Consultor',
            'allocation_percent' => 50,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'hourly_rate' => $hourlyRate,
            'total_hours_planned' => 120,
            'total_hours_logged' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_can_list_time_entries_with_pagination(): void
    {
        $this->actingAsUser();
        $project = $this->createProject();
        $resourceId = $this->createResource($project);

        foreach (range(1, 12) as $index) {
            DB::table('project_time_entries')->insert([
                'tenant_id' => $this->tenant->id,
                'project_id' => $project->id,
                'project_resource_id' => $resourceId,
                'date' => '2026-04-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'hours' => 2.5,
                'billable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->getJson("/api/v1/projects/{$project->id}/time-entries?per_page=10")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'project_resource_id',
                    'date',
                    'hours',
                    'billable',
                ]],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_can_create_billable_time_entry_and_increment_project_spent(): void
    {
        $this->actingAsUser();
        $project = $this->createProject();
        $resourceId = $this->createResource($project, 150);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'created_by' => $this->user->id,
        ]);

        $this->postJson("/api/v1/projects/{$project->id}/time-entries", [
            'project_resource_id' => $resourceId,
            'work_order_id' => $workOrder->id,
            'date' => '2026-04-05',
            'hours' => 6.5,
            'billable' => true,
            'description' => 'Execucao em campo',
        ])->assertCreated()
            ->assertJsonPath('data.work_order_id', $workOrder->id);

        $this->assertDatabaseHas('project_time_entries', [
            'project_id' => $project->id,
            'project_resource_id' => $resourceId,
            'hours' => '6.50',
            'billable' => 1,
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'spent' => '975.00',
        ]);
    }

    public function test_non_billable_time_entry_does_not_increment_project_spent(): void
    {
        $this->actingAsUser();
        $project = $this->createProject();
        $resourceId = $this->createResource($project, 200);

        $this->postJson("/api/v1/projects/{$project->id}/time-entries", [
            'project_resource_id' => $resourceId,
            'date' => '2026-04-06',
            'hours' => 3,
            'billable' => false,
        ])->assertCreated();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'spent' => '0.00',
        ]);
    }

    public function test_store_validates_time_entry_payload(): void
    {
        $this->actingAsUser();
        $project = $this->createProject();

        $this->postJson("/api/v1/projects/{$project->id}/time-entries", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_resource_id', 'date', 'hours', 'billable']);
    }

    public function test_can_update_time_entry_and_recalculate_spent(): void
    {
        $this->actingAsUser();
        $project = $this->createProject();
        $resourceId = $this->createResource($project, 100);

        $entryId = DB::table('project_time_entries')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'project_resource_id' => $resourceId,
            'date' => '2026-04-01',
            'hours' => 2,
            'billable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('projects')->where('id', $project->id)->update(['spent' => 200]);

        $this->putJson("/api/v1/projects/{$project->id}/time-entries/{$entryId}", [
            'project_resource_id' => $resourceId,
            'date' => '2026-04-01',
            'hours' => 5,
            'billable' => true,
        ])->assertOk()
            ->assertJsonPath('data.hours', '5.00');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'spent' => '500.00',
        ]);
    }

    public function test_can_delete_time_entry_and_recalculate_spent(): void
    {
        $this->actingAsUser();
        $project = $this->createProject();
        $resourceId = $this->createResource($project, 100);

        $entryId = DB::table('project_time_entries')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'project_resource_id' => $resourceId,
            'date' => '2026-04-01',
            'hours' => 4,
            'billable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('projects')->where('id', $project->id)->update(['spent' => 400]);

        $this->deleteJson("/api/v1/projects/{$project->id}/time-entries/{$entryId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('project_time_entries', ['id' => $entryId]);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'spent' => '0.00',
        ]);
    }

    public function test_project_time_entries_respect_tenant_isolation(): void
    {
        $this->actingAsUser();

        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $otherTenant->id])->id,
        ]);

        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $resourceId = DB::table('project_resources')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'user_id' => $otherUser->id,
            'role' => 'Outro tenant',
            'allocation_percent' => 50,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'hourly_rate' => 100,
            'total_hours_planned' => 100,
            'total_hours_logged' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entryId = DB::table('project_time_entries')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'project_resource_id' => $resourceId,
            'date' => '2026-04-01',
            'hours' => 3,
            'billable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/projects/{$otherProject->id}/time-entries")->assertNotFound();
        $this->deleteJson("/api/v1/projects/{$otherProject->id}/time-entries/{$entryId}")->assertNotFound();
    }

    public function test_requires_authentication_and_permissions_for_time_entries(): void
    {
        $project = $this->createProject();

        $this->getJson("/api/v1/projects/{$project->id}/time-entries")->assertUnauthorized();

        Gate::before(fn () => false);
        $this->actingAsUser();
        $this->withMiddleware([CheckPermission::class]);

        $response = $this->getJson("/api/v1/projects/{$project->id}/time-entries");
        $this->assertContains($response->status(), [403, 404]);
    }
}
