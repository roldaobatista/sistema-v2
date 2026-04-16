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
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderChatControllerTest extends TestCase
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
        Storage::fake('public');

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_chat_messages(): void
    {
        WorkOrderChat::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'message' => 'Primeira',
            'type' => 'text',
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/chats");

        $response->assertOk()->assertJsonStructure(['data']);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_store_validates_required_message(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/chats", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_store_creates_text_message(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/chats", [
            'message' => 'Olá técnico',
            'type' => 'text',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('work_order_chats', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'message' => 'Olá técnico',
            'type' => 'text',
        ]);
    }

    public function test_mark_as_read_updates_other_users_messages(): void
    {
        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherUser->tenants()->attach($this->tenant->id);

        // Mensagem de outro usuário, não lida
        $chat = WorkOrderChat::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $otherUser->id,
            'message' => 'Não lida',
            'type' => 'text',
        ]);

        $this->assertNull($chat->fresh()->read_at);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/chats/read");

        $response->assertOk();
        $this->assertNotNull($chat->fresh()->read_at);
    }

    public function test_index_returns_404_for_cross_tenant_work_order(): void
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

        $response = $this->getJson("/api/v1/work-orders/{$foreignWo->id}/chats");

        $response->assertStatus(404);
    }
}
