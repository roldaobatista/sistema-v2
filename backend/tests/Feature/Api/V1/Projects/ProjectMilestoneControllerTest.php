<?php

namespace Tests\Feature\Api\V1\Projects;

require_once __DIR__.'/ProjectsFeatureTestCase.php';

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProjectMilestoneControllerTest extends ProjectsFeatureTestCase
{
    private function createProject(): Project
    {
        return Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'created_by' => $this->user->id,
            'status' => 'active',
            'budget' => 10000,
            'progress_percent' => 0,
            'spent' => 0,
        ]);
    }

    public function test_can_list_milestones_with_pagination(): void
    {
        $this->actingAsUser();

        $project = $this->createProject();

        foreach (range(1, 12) as $index) {
            DB::table('project_milestones')->insert([
                'tenant_id' => $this->tenant->id,
                'project_id' => $project->id,
                'name' => "Milestone {$index}",
                'status' => 'pending',
                'order' => $index,
                'weight' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->getJson("/api/v1/projects/{$project->id}/milestones?per_page=10");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'name',
                    'status',
                    'order',
                    'weight',
                ]],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_can_create_milestone(): void
    {
        $this->actingAsUser();

        $project = $this->createProject();

        $response = $this->postJson("/api/v1/projects/{$project->id}/milestones", [
            'name' => 'Kickoff',
            'planned_start' => '2026-04-01',
            'planned_end' => '2026-04-10',
            'billing_value' => 2500,
            'weight' => 1.5,
            'order' => 1,
            'deliverables' => 'Plano aprovado',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Kickoff')
            ->assertJsonPath('data.billing_value', '2500.00');

        $this->assertDatabaseHas('project_milestones', [
            'project_id' => $project->id,
            'name' => 'Kickoff',
            'status' => 'pending',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsUser();

        $project = $this->createProject();

        $response = $this->postJson("/api/v1/projects/{$project->id}/milestones", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'order']);
    }

    public function test_cannot_complete_milestone_with_pending_dependencies(): void
    {
        $this->actingAsUser();

        $project = $this->createProject();

        $dependencyId = DB::table('project_milestones')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Dependencia',
            'status' => 'pending',
            'order' => 1,
            'weight' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $milestoneId = DB::table('project_milestones')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Principal',
            'status' => 'pending',
            'order' => 2,
            'weight' => 1,
            'dependencies' => json_encode([$dependencyId], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/api/v1/projects/{$project->id}/milestones/{$milestoneId}/complete")
            ->assertStatus(422);
    }

    public function test_completing_milestone_recalculates_project_progress(): void
    {
        $this->actingAsUser();

        $project = $this->createProject();

        $firstMilestoneId = DB::table('project_milestones')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Fase 1',
            'status' => 'in_progress',
            'order' => 1,
            'weight' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_milestones')->insert([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Fase 2',
            'status' => 'pending',
            'order' => 2,
            'weight' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/api/v1/projects/{$project->id}/milestones/{$firstMilestoneId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'progress_percent' => '50.00',
        ]);
    }

    public function test_generate_invoice_creates_draft_invoice_and_marks_milestone_as_invoiced(): void
    {
        $this->actingAsUser();

        $project = $this->createProject();

        $milestoneId = DB::table('project_milestones')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Entrega 1',
            'status' => 'completed',
            'order' => 1,
            'weight' => 1,
            'billing_value' => 3200,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/projects/{$project->id}/milestones/{$milestoneId}/invoice");

        $response->assertOk()
            ->assertJsonPath('data.status', 'invoiced');

        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $project->customer_id,
            'status' => 'draft',
            'total' => '3200.00',
        ]);
    }

    public function test_project_milestones_respect_tenant_isolation(): void
    {
        $this->actingAsUser();

        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $otherTenant->id])->id,
        ]);

        $milestoneId = DB::connection('sqlite')->table('project_milestones')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'name' => 'Outro tenant',
            'status' => 'pending',
            'order' => 1,
            'weight' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/projects/{$otherProject->id}/milestones")->assertNotFound();
        $this->postJson("/api/v1/projects/{$otherProject->id}/milestones/{$milestoneId}/complete")->assertNotFound();
    }

    public function test_requires_authentication_and_permissions_for_milestones(): void
    {
        $project = $this->createProject();

        $this->getJson("/api/v1/projects/{$project->id}/milestones")->assertUnauthorized();

        Gate::before(fn () => false);
        $this->actingAsUser();
        $this->withMiddleware([CheckPermission::class]);

        $response = $this->getJson("/api/v1/projects/{$project->id}/milestones");
        $this->assertContains($response->status(), [403, 404]);
    }
}
