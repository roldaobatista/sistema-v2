<?php

namespace Tests\Feature\Api\V1\Operational;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Checklist;
use App\Models\ChecklistSubmission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChecklistSubmissionControllerTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createChecklist(array $overrides = []): Checklist
    {
        return Checklist::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
        ], $overrides));
    }

    private function createSubmission(array $overrides = []): ChecklistSubmission
    {
        $checklist = $overrides['checklist_id'] ?? $this->createChecklist()->id;
        unset($overrides['checklist_id']);

        return ChecklistSubmission::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'checklist_id' => $checklist,
            'technician_id' => $this->user->id,
            'responses' => ['item_1' => true, 'item_2' => 'photo.jpg'],
            'completed_at' => now(),
        ], $overrides));
    }

    // ─── INDEX ────────────────────────────────────────────────────────

    public function test_index_returns_all_submissions(): void
    {
        $this->createSubmission();
        $this->createSubmission();

        $response = $this->getJson('/api/v1/checklist-submissions');

        $response->assertOk()
            ->assertJsonStructure(['data']);
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_index_filters_by_work_order_id(): void
    {
        $workOrder = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->createSubmission(['work_order_id' => $workOrder->id]);
        $this->createSubmission(); // no work order

        $response = $this->getJson("/api/v1/checklist-submissions?work_order_id={$workOrder->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($workOrder->id, $data[0]['work_order_id']);
    }

    public function test_index_filters_by_technician_id(): void
    {
        $otherTech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->createSubmission(['technician_id' => $this->user->id]);
        $this->createSubmission(['technician_id' => $otherTech->id]);

        $response = $this->getJson("/api/v1/checklist-submissions?technician_id={$this->user->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->user->id, $data[0]['technician_id']);
    }

    public function test_index_includes_relations(): void
    {
        $checklist = $this->createChecklist(['name' => 'Checklist Visita']);
        $this->createSubmission(['checklist_id' => $checklist->id]);

        $response = $this->getJson('/api/v1/checklist-submissions');

        $response->assertOk();
        $first = $response->json('data.0');
        $this->assertArrayHasKey('checklist', $first);
        $this->assertArrayHasKey('technician', $first);
    }

    // ─── STORE ────────────────────────────────────────────────────────

    public function test_store_creates_submission(): void
    {
        $checklist = $this->createChecklist();

        $response = $this->postJson('/api/v1/checklist-submissions', [
            'checklist_id' => $checklist->id,
            'responses' => ['item_1' => true, 'item_2' => 'foto.jpg', 'item_3' => 'Tudo OK'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('checklist_submissions', [
            'checklist_id' => $checklist->id,
            'technician_id' => $this->user->id,
        ]);
    }

    public function test_store_sets_technician_to_current_user(): void
    {
        $checklist = $this->createChecklist();

        $response = $this->postJson('/api/v1/checklist-submissions', [
            'checklist_id' => $checklist->id,
            'responses' => ['item_1' => false],
        ]);

        $response->assertStatus(201);
        $this->assertEquals($this->user->id, $response->json('data.technician_id'));
    }

    public function test_store_with_work_order(): void
    {
        $checklist = $this->createChecklist();
        $workOrder = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/checklist-submissions', [
            'checklist_id' => $checklist->id,
            'work_order_id' => $workOrder->id,
            'responses' => ['item_1' => true],
        ]);

        $response->assertStatus(201);
        $this->assertEquals($workOrder->id, $response->json('data.work_order_id'));
    }

    public function test_store_sets_completed_at_default(): void
    {
        $checklist = $this->createChecklist();

        $response = $this->postJson('/api/v1/checklist-submissions', [
            'checklist_id' => $checklist->id,
            'responses' => ['item_1' => true],
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.completed_at'));
    }

    public function test_store_accepts_custom_completed_at(): void
    {
        $checklist = $this->createChecklist();
        $date = '2026-03-01 10:30:00';

        $response = $this->postJson('/api/v1/checklist-submissions', [
            'checklist_id' => $checklist->id,
            'responses' => ['item_1' => true],
            'completed_at' => $date,
        ]);

        $response->assertStatus(201);
        $this->assertStringContainsString('2026-03-01', $response->json('data.completed_at'));
    }

    public function test_store_validation_requires_checklist_id(): void
    {
        $response = $this->postJson('/api/v1/checklist-submissions', [
            'responses' => ['item_1' => true],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['checklist_id']);
    }

    public function test_store_validation_requires_responses(): void
    {
        $checklist = $this->createChecklist();

        $response = $this->postJson('/api/v1/checklist-submissions', [
            'checklist_id' => $checklist->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['responses']);
    }

    public function test_store_validation_rejects_invalid_checklist_id(): void
    {
        $response = $this->postJson('/api/v1/checklist-submissions', [
            'checklist_id' => 999999,
            'responses' => ['item_1' => true],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['checklist_id']);
    }

    // ─── SHOW ─────────────────────────────────────────────────────────

    public function test_show_returns_submission_with_relations(): void
    {
        $checklist = $this->createChecklist(['name' => 'Detail Checklist']);
        $submission = $this->createSubmission(['checklist_id' => $checklist->id]);

        $response = $this->getJson("/api/v1/checklist-submissions/{$submission->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $submission->id);
        $this->assertArrayHasKey('checklist', $response->json('data'));
        $this->assertArrayHasKey('technician', $response->json('data'));
    }

    // ─── AUTH ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_gets_401(): void
    {
        Sanctum::actingAs(new User, []);
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/checklist-submissions');

        $response->assertUnauthorized();
    }
}
