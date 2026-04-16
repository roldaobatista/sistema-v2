<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderChatTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $user;

    private User $otherUser;

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
        $this->otherTenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);
    }

    // ── INDEX ──

    public function test_index_returns_chat_messages_for_work_order(): void
    {
        WorkOrderChat::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'message' => 'Primeira mensagem',
            'type' => 'text',
        ]);
        WorkOrderChat::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->otherUser->id,
            'message' => 'Segunda mensagem',
            'type' => 'text',
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/chats");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.message', 'Primeira mensagem');
        $response->assertJsonPath('data.1.message', 'Segunda mensagem');
    }

    public function test_index_returns_messages_ordered_by_created_at_asc(): void
    {
        $older = WorkOrderChat::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'message' => 'Mensagem antiga',
            'type' => 'text',
        ]);
        // Force older timestamp via DB update to avoid mass-assignment issue
        DB::table('work_order_chats')
            ->where('id', $older->id)
            ->update(['created_at' => now()->subMinutes(10)]);

        $newer = WorkOrderChat::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'message' => 'Mensagem nova',
            'type' => 'text',
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/chats");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($older->id, $data[0]['id']);
        $this->assertEquals($newer->id, $data[1]['id']);
    }

    public function test_index_returns_empty_for_work_order_with_no_messages(): void
    {
        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/chats");

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_index_tenant_isolation_blocks_other_tenant_work_order(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$otherWo->id}/chats");

        $response->assertStatus(404);
    }

    // ── STORE ──

    public function test_store_creates_text_message(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/chats", [
            'message' => 'Mensagem de teste',
            'type' => 'text',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.message', 'Mensagem de teste');
        $response->assertJsonPath('data.type', 'text');
        $response->assertJsonPath('data.user_id', $this->user->id);
        $response->assertJsonPath('data.work_order_id', $this->workOrder->id);

        $this->assertDatabaseHas('work_order_chats', [
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'message' => 'Mensagem de teste',
            'type' => 'text',
        ]);
    }

    public function test_store_defaults_type_to_text(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/chats", [
            'message' => 'Mensagem sem tipo',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.type', 'text');
    }

    public function test_store_validates_message_required(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/chats", [
            'type' => 'text',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_sets_tenant_id_from_work_order(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/chats", [
            'message' => 'Checando tenant',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('work_order_chats', [
            'work_order_id' => $this->workOrder->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_tenant_isolation_blocks_other_tenant_work_order(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$otherWo->id}/chats", [
            'message' => 'Tentativa cross-tenant',
        ]);

        $response->assertStatus(404);
    }

    // ── MARK AS READ ──

    public function test_mark_as_read_marks_other_users_messages(): void
    {
        $msg1 = WorkOrderChat::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->otherUser->id,
            'message' => 'Mensagem do outro',
            'type' => 'text',
        ]);
        $myMsg = WorkOrderChat::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'message' => 'Minha mensagem',
            'type' => 'text',
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/chats/read");

        $response->assertOk();

        // Other user's message should be marked as read
        $this->assertNotNull($msg1->fresh()->read_at);
        // My own message should NOT be marked as read
        $this->assertNull($myMsg->fresh()->read_at);
    }

    public function test_mark_as_read_does_not_re_mark_already_read_messages(): void
    {
        $readAt = now()->subHour();
        $msg = WorkOrderChat::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->otherUser->id,
            'message' => 'Já lida',
            'type' => 'text',
            'read_at' => $readAt,
        ]);

        $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/chats/read");

        // read_at should keep the original value (was already set)
        $this->assertEquals(
            $readAt->format('Y-m-d H:i:s'),
            $msg->fresh()->read_at->format('Y-m-d H:i:s')
        );
    }

    public function test_mark_as_read_tenant_isolation(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$otherWo->id}/chats/read");

        $response->assertStatus(404);
    }
}
