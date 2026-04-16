<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\ServiceChecklist;
use App\Models\ServiceChecklistItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderApprovalTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private WorkOrder $workOrder;

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

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);
    }

    // ── INDEX ──

    public function test_index_returns_approvals_for_work_order(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        DB::table('work_order_approvals')->insert([
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver->id,
            'requested_by' => $this->user->id,
            'status' => 'pending',
            'notes' => 'Please approve',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/approvals");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'work_order_id', 'approver_id', 'status', 'notes', 'approver_name'],
                ],
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('pending', $response->json('data.0.status'));
        $this->assertEquals($approver->name, $response->json('data.0.approver_name'));
    }

    public function test_index_returns_empty_when_no_approvals(): void
    {
        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/approvals");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_index_rejects_other_tenant_work_order(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$otherWo->id}/approvals");

        $response->assertStatus(404);
    }

    // ── REQUEST APPROVAL ──

    public function test_request_creates_approval_records(): void
    {
        $approver1 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $approver2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", [
            'approver_ids' => [$approver1->id, $approver2->id],
            'notes' => 'Need approval for this WO',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['approval_ids'],
                'message',
            ]);

        $this->assertCount(2, $response->json('data.approval_ids'));

        $this->assertDatabaseHas('work_order_approvals', [
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver1->id,
            'status' => 'pending',
            'notes' => 'Need approval for this WO',
            'requested_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('work_order_approvals', [
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver2->id,
            'status' => 'pending',
        ]);

        // WO status should change to waiting_approval
        $this->assertEquals(WorkOrder::STATUS_WAITING_APPROVAL, $this->workOrder->fresh()->status);
    }

    public function test_request_fails_without_approver_ids(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", [
            'notes' => 'Missing approvers',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['approver_ids']);
    }

    public function test_request_fails_with_empty_approver_ids(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", [
            'approver_ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['approver_ids']);
    }

    public function test_request_fails_with_invalid_approver_id(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", [
            'approver_ids' => [99999],
        ]);

        $response->assertStatus(422);
    }

    public function test_request_fails_when_approver_is_from_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", [
            'approver_ids' => [$otherUser->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_request_accepts_nullable_notes(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", [
            'approver_ids' => [$approver->id],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('work_order_approvals', [
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver->id,
            'notes' => null,
        ]);
    }

    public function test_request_fails_when_there_is_already_pending_approval(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        DB::table('work_order_approvals')->insert([
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver->id,
            'requested_by' => $this->user->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->workOrder->update(['status' => WorkOrder::STATUS_WAITING_APPROVAL]);

        $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/approvals/request", [
            'approver_ids' => [$approver->id],
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Já existe aprovação pendente para esta OS.']);
    }

    // ── RESPOND: APPROVE ──

    public function test_respond_approve_marks_approval_as_approved(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        DB::table('work_order_approvals')->insert([
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver->id,
            'requested_by' => $this->user->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->workOrder->update(['status' => WorkOrder::STATUS_WAITING_APPROVAL]);
        Sanctum::actingAs($approver, ['*']);

        $response = $this->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/approvals/{$approver->id}/approve"
        );

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Aprovado']);

        $this->assertDatabaseHas('work_order_approvals', [
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver->id,
            'status' => 'approved',
        ]);

        // Since this was the only pending approval, WO should be completed
        $this->assertEquals(WorkOrder::STATUS_COMPLETED, $this->workOrder->fresh()->status);
    }

    public function test_respond_approve_does_not_complete_when_others_pending(): void
    {
        $approver1 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $approver2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        foreach ([$approver1, $approver2] as $approver) {
            DB::table('work_order_approvals')->insert([
                'work_order_id' => $this->workOrder->id,
                'approver_id' => $approver->id,
                'requested_by' => $this->user->id,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->workOrder->update(['status' => WorkOrder::STATUS_WAITING_APPROVAL]);
        Sanctum::actingAs($approver1, ['*']);

        $response = $this->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/approvals/{$approver1->id}/approve"
        );

        $response->assertStatus(200);

        // Approver1 approved, but approver2 is still pending, so WO should stay in waiting_approval
        $this->assertEquals(WorkOrder::STATUS_WAITING_APPROVAL, $this->workOrder->fresh()->status);
    }

    // ── RESPOND: REJECT ──

    public function test_respond_reject_cancels_all_pending_and_reopens_wo(): void
    {
        $approver1 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $approver2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        foreach ([$approver1, $approver2] as $approver) {
            DB::table('work_order_approvals')->insert([
                'work_order_id' => $this->workOrder->id,
                'approver_id' => $approver->id,
                'requested_by' => $this->user->id,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->workOrder->update(['status' => WorkOrder::STATUS_WAITING_APPROVAL]);
        $this->workOrder->statusHistory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'from_status' => WorkOrder::STATUS_COMPLETED,
            'to_status' => WorkOrder::STATUS_WAITING_APPROVAL,
            'notes' => 'Solicitação de aprovação',
        ]);
        Sanctum::actingAs($approver1, ['*']);

        $response = $this->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/approvals/{$approver1->id}/reject",
            ['notes' => 'Not acceptable']
        );

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Rejeitado']);

        // Approver1's record rejected
        $this->assertDatabaseHas('work_order_approvals', [
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver1->id,
            'status' => 'rejected',
        ]);

        // Approver2's pending record cancelled
        $this->assertDatabaseHas('work_order_approvals', [
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver2->id,
            'status' => 'cancelled',
        ]);

        // WO returns to previous status
        $this->assertEquals(WorkOrder::STATUS_COMPLETED, $this->workOrder->fresh()->status);
    }

    public function test_respond_approve_blocks_final_completion_when_checklist_is_incomplete(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $checklist = ServiceChecklist::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Checklist aprovação',
            'is_active' => true,
        ]);

        ServiceChecklistItem::create([
            'checklist_id' => $checklist->id,
            'description' => 'Passo obrigatório',
            'type' => 'text',
            'is_required' => true,
            'order_index' => 1,
        ]);

        DB::table('work_order_approvals')->insert([
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver->id,
            'requested_by' => $this->user->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->workOrder->update([
            'status' => WorkOrder::STATUS_WAITING_APPROVAL,
            'checklist_id' => $checklist->id,
        ]);
        Sanctum::actingAs($approver, ['*']);

        $this->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/approvals/{$approver->id}/approve"
        )->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'O checklist da OS está incompleto. Todos os itens obrigatórios devem ser respondidos antes da aprovação final.',
            ]);

        $this->assertDatabaseHas('work_order_approvals', [
            'work_order_id' => $this->workOrder->id,
            'approver_id' => $approver->id,
            'status' => 'pending',
        ]);

        $this->assertEquals(WorkOrder::STATUS_WAITING_APPROVAL, $this->workOrder->fresh()->status);
    }

    public function test_respond_with_invalid_action_returns_422(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        Sanctum::actingAs($approver, ['*']);

        $response = $this->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/approvals/{$approver->id}/invalid_action"
        );

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Ação inválida']);
    }

    public function test_respond_returns_404_when_no_pending_approval(): void
    {
        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->workOrder->update(['status' => WorkOrder::STATUS_WAITING_APPROVAL]);
        Sanctum::actingAs($approver, ['*']);

        $response = $this->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/approvals/{$approver->id}/approve"
        );

        $response->assertStatus(404)
            ->assertJsonFragment(['message' => 'Aprovação pendente não encontrada.']);
    }

    public function test_respond_fails_when_approver_not_in_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $response = $this->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/approvals/{$otherUser->id}/approve"
        );

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Voce so pode responder a sua propria aprovacao.']);
    }

    // ── AUTHENTICATION ──

    public function test_unauthenticated_request_returns_401(): void
    {
        Sanctum::actingAs(new User, []);
        $this->app['auth']->forgetGuards();

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/approvals");

        $response->assertStatus(401);
    }
}
