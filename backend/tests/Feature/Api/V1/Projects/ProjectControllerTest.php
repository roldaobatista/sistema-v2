<?php

namespace Tests\Feature\Api\V1\Projects;

require_once __DIR__.'/ProjectsFeatureTestCase.php';

use App\Http\Middleware\CheckPermission;
use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Support\Facades\Gate;

class ProjectControllerTest extends ProjectsFeatureTestCase
{
    public function test_can_list_projects_with_pagination(): void
    {
        $this->actingAsUser();

        Project::factory()->count(25)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/projects?per_page=15');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'status']], 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);

        $this->assertCount(15, $response->json('data'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_can_filter_projects_by_status_and_customer(): void
    {
        $this->actingAsUser();

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Project::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'draft']);
        Project::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active', 'customer_id' => $customer->id]);

        $response = $this->getJson("/api/v1/projects?status=active&customer_id={$customer->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('active', $response->json('data.0.status'));
        $this->assertEquals($customer->id, $response->json('data.0.customer_id'));
    }

    public function test_can_create_project_with_valid_data(): void
    {
        $this->actingAsUser();

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $payload = [
            'name' => 'New ERP Implementation',
            'description' => 'Full deployment of modules',
            'status' => 'planning',
            'priority' => 'high',
            'customer_id' => $customer->id,
            'manager_id' => $this->user->id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-12-31',
            'budget' => 50000.00,
            'billing_type' => 'milestone',
        ];

        $response = $this->postJson('/api/v1/projects', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'PRJ-00001')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.billing_type', 'milestone');
        $this->assertDatabaseHas('projects', [
            'tenant_id' => $this->tenant->id,
            'name' => 'New ERP Implementation',
            'status' => 'planning',
            'budget' => 50000.00,
            'code' => 'PRJ-00001',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_create_project_validates_payload(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/v1/projects', [
            'name' => '', // Required
            // missing status, customer_id etc
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'status', 'priority', 'billing_type', 'customer_id']);
    }

    public function test_can_show_project(): void
    {
        $this->actingAsUser();

        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson("/api/v1/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.name', $project->name);
    }

    public function test_can_update_project(): void
    {
        $this->actingAsUser();

        $project = Project::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Old Name', 'status' => 'draft']);

        $payload = [
            'name' => 'Updated Project Name',
            'status' => 'active',
            'priority' => 'critical',
        ];

        $response = $this->putJson("/api/v1/projects/{$project->id}", $payload);

        $response->assertOk();
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'status' => 'active',
            'priority' => 'critical',
        ]);
    }

    public function test_can_delete_project(): void
    {
        $this->actingAsUser();

        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_project_tenant_isolation(): void
    {
        $this->actingAsUser();

        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);

        // List isolation
        $resList = $this->getJson('/api/v1/projects');
        $resList->assertOk();
        $this->assertEmpty($resList->json('data'));

        // Show isolation (404 for cross tenant)
        $resShow = $this->getJson("/api/v1/projects/{$otherProject->id}");
        $resShow->assertNotFound();

        // Update isolation
        $resUpdate = $this->putJson("/api/v1/projects/{$otherProject->id}", ['name' => 'Hacked', 'status' => 'active']);
        $resUpdate->assertNotFound();
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/projects');
        $response->assertUnauthorized();
    }

    public function test_respects_permissions_403(): void
    {
        Gate::before(fn () => false); // Deny all
        $this->actingAsUser();
        $this->withMiddleware([CheckPermission::class]);

        $response = $this->getJson('/api/v1/projects');
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_can_create_project_with_originating_crm_deal(): void
    {
        $this->actingAsUser();

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Programa de Expansao',
            'status' => 'planning',
            'priority' => 'medium',
            'billing_type' => 'fixed_price',
            'customer_id' => $customer->id,
            'manager_id' => $this->user->id,
            'crm_deal_id' => $deal->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-06-30',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.crm_deal_id', $deal->id);

        $this->assertDatabaseHas('projects', [
            'name' => 'Programa de Expansao',
            'crm_deal_id' => $deal->id,
        ]);
    }

    public function test_can_transition_project_status_through_lifecycle(): void
    {
        $this->actingAsUser();

        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'planning',
        ]);

        $this->postJson("/api/v1/projects/{$project->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->postJson("/api/v1/projects/{$project->id}/pause")
            ->assertOk()
            ->assertJsonPath('data.status', 'on_hold');

        $this->postJson("/api/v1/projects/{$project->id}/resume")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->postJson("/api/v1/projects/{$project->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_dashboard_returns_portfolio_health_summary(): void
    {
        $this->actingAsUser();

        Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'budget' => 10000,
            'spent' => 4000,
            'progress_percent' => 40,
        ]);
        Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'completed',
            'budget' => 8000,
            'spent' => 7800,
            'progress_percent' => 100,
        ]);

        $response = $this->getJson('/api/v1/projects/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_projects',
                    'active_projects',
                    'completed_projects',
                    'budget_total',
                    'spent_total',
                    'average_progress',
                    'status_breakdown',
                ],
            ])
            ->assertJsonPath('data.total_projects', 2)
            ->assertJsonPath('data.active_projects', 1)
            ->assertJsonPath('data.completed_projects', 1);
    }

    public function test_gantt_returns_project_milestones_and_resources(): void
    {
        $this->actingAsUser();

        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/v1/projects/{$project->id}/gantt");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'project' => ['id', 'name', 'status', 'start_date', 'end_date'],
                    'milestones',
                    'resources',
                    'time_entries',
                ],
            ]);
    }
}
