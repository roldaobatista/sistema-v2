<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChat;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderCommentControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_comments_lists_only_work_order_comments(): void
    {
        WorkOrderChat::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'message' => 'Primeiro comentário',
            'type' => 'comment',
        ]);
        WorkOrderChat::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'message' => 'Segundo comentário',
            'type' => 'comment',
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/comments");

        $response->assertOk()->assertJsonStructure(['data']);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_store_comment_validates_required_content(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/comments", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_store_comment_rejects_content_above_5000_chars(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/comments", [
            'content' => str_repeat('a', 5001),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_store_comment_creates_with_user_and_tenant(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/comments", [
            'content' => 'Comentário de teste',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('work_order_chats', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'message' => 'Comentário de teste',
            'type' => 'comment',
        ]);
    }

    public function test_store_comment_returns_404_for_cross_tenant_work_order(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $foreignWo = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$foreignWo->id}/comments", [
            'content' => 'Não deveria vazar',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('work_order_chats', [
            'work_order_id' => $foreignWo->id,
            'message' => 'Não deveria vazar',
        ]);
    }
}
