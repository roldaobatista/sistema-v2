<?php

namespace Tests\Feature\Api\V1\Operational;

use App\Events\WorkOrderStatusChanged;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpressWorkOrderTest extends TestCase
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

    // ───── STORE with existing customer ─────

    public function test_store_creates_express_wo_with_existing_customer(): void
    {
        Event::fake();

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_id' => $customer->id,
            'description' => 'Reparo urgente no equipamento',
            'priority' => 'urgent',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', WorkOrder::STATUS_OPEN);
        $response->assertJsonPath('data.customer_id', $customer->id);
        $response->assertJsonStructure([
            'data' => ['id', 'number', 'status', 'priority', 'description'],
            'message',
        ]);

        $this->assertDatabaseHas('work_orders', [
            'customer_id' => $customer->id,
            'tenant_id' => $this->tenant->id,
            'status' => WorkOrder::STATUS_OPEN,
            'origin_type' => 'manual',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);
    }

    // ───── STORE creates new customer ─────

    public function test_store_creates_customer_when_customer_id_is_null(): void
    {
        Event::fake();

        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_name' => 'Cliente Novo Express',
            'description' => 'Instalação rápida',
            'priority' => 'normal',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'name' => 'Cliente Novo Express',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'type' => 'PF',
        ]);

        $woId = $response->json('data.id');
        $wo = WorkOrder::find($woId);
        $this->assertNotNull($wo->customer_id);
    }

    // ───── Creates status history ─────

    public function test_store_creates_initial_status_history(): void
    {
        Event::fake();

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_id' => $customer->id,
            'description' => 'Teste histórico',
            'priority' => 'low',
        ]);

        $response->assertStatus(201);

        $woId = $response->json('data.id');

        $this->assertDatabaseHas('work_order_status_history', [
            'work_order_id' => $woId,
            'from_status' => null,
            'to_status' => WorkOrder::STATUS_OPEN,
            'notes' => 'OS Express criada',
            'user_id' => $this->user->id,
        ]);
    }

    // ───── Dispatches event ─────

    public function test_store_dispatches_work_order_status_changed_event(): void
    {
        Event::fake([WorkOrderStatusChanged::class]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_id' => $customer->id,
            'description' => 'Evento dispatch test',
            'priority' => 'high',
        ]);

        Event::assertDispatched(WorkOrderStatusChanged::class);
    }

    // ───── Validation ─────

    public function test_store_validates_description_required(): void
    {
        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_name' => 'Teste',
            'priority' => 'normal',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description']);
    }

    public function test_store_validates_priority_required(): void
    {
        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_name' => 'Teste',
            'description' => 'Teste',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['priority']);
    }

    public function test_store_validates_priority_values(): void
    {
        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_name' => 'Teste',
            'description' => 'Teste',
            'priority' => 'super-urgent',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['priority']);
    }

    public function test_store_requires_customer_name_when_no_customer_id(): void
    {
        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'description' => 'Sem cliente',
            'priority' => 'normal',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_name']);
    }

    public function test_store_validates_customer_id_exists_in_same_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_id' => $otherCustomer->id,
            'description' => 'Cross-tenant attack',
            'priority' => 'normal',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id']);
    }

    // ───── Assigns user ─────

    public function test_store_assigns_work_order_to_current_user(): void
    {
        Event::fake();

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_id' => $customer->id,
            'description' => 'Auto-assign test',
            'priority' => 'normal',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.assigned_to', $this->user->id);
    }

    // ───── Auth ─────

    public function test_unauthenticated_user_cannot_create_express_wo(): void
    {
        app('auth')->forgetGuards();

        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_name' => 'Hacker',
            'description' => 'Should fail',
            'priority' => 'high',
        ]);

        $response->assertStatus(401);
    }
}
