<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for work-order item management (storeItem, updateItem, destroyItem)
 * handled by WorkOrderController via nested routes.
 *
 * Routes:
 *   GET    /api/v1/work-orders/{workOrder}/items
 *   POST   /api/v1/work-orders/{workOrder}/items
 *   PUT    /api/v1/work-orders/{workOrder}/items/{item}
 *   DELETE /api/v1/work-orders/{workOrder}/items/{item}
 */
class WorkOrderItemControllerTest extends TestCase
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
            'is_active' => true,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
    }

    // ── GET items ──────────────────────────────────────────────

    public function test_can_list_work_order_items(): void
    {
        WorkOrderItem::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/items");

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    // ── POST storeItem ─────────────────────────────────────────

    public function test_can_add_service_item_to_work_order(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/items", [
            'type' => 'service',
            'description' => 'Mão de obra técnica',
            'quantity' => 1,
            'unit_price' => 150.00,
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'description' => 'Mão de obra técnica',
            'type' => 'service',
        ]);

        $this->assertDatabaseHas('work_order_items', [
            'work_order_id' => $this->workOrder->id,
            'description' => 'Mão de obra técnica',
        ]);
    }

    public function test_store_item_requires_description(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/items", [
            'type' => 'service',
            'unit_price' => 100.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_store_item_requires_valid_type(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/items", [
            'type' => 'invalid_type',
            'description' => 'Test item',
            'unit_price' => 100.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_item_discount_cannot_exceed_total(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/items", [
            'type' => 'service',
            'description' => 'Serviço com desconto inválido',
            'quantity' => 1,
            'unit_price' => 100.00,
            'discount' => 200.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discount']);
    }

    public function test_store_item_defaults_type_to_service(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/items", [
            'description' => 'Item sem type explícito',
            'unit_price' => 50.00,
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['type' => 'service']);
    }

    // ── PUT updateItem ─────────────────────────────────────────

    public function test_can_update_work_order_item(): void
    {
        $item = WorkOrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'type' => 'service',
            'description' => 'Original description',
            'unit_price' => 100.00,
            'quantity' => 1,
        ]);

        $response = $this->putJson(
            "/api/v1/work-orders/{$this->workOrder->id}/items/{$item->id}",
            ['description' => 'Updated description', 'unit_price' => 200.00]
        );

        $response->assertStatus(200);
        $response->assertJsonFragment(['description' => 'Updated description']);

        $this->assertDatabaseHas('work_order_items', [
            'id' => $item->id,
            'description' => 'Updated description',
        ]);
    }

    public function test_cannot_update_item_belonging_to_other_work_order(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $item = WorkOrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $otherWorkOrder->id,
        ]);

        $response = $this->putJson(
            "/api/v1/work-orders/{$this->workOrder->id}/items/{$item->id}",
            ['description' => 'Hacked update']
        );

        $response->assertStatus(403);
    }

    // ── DELETE destroyItem ─────────────────────────────────────

    public function test_can_delete_work_order_item(): void
    {
        $item = WorkOrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'type' => 'service',
            'description' => 'Item a deletar',
            'unit_price' => 80.00,
            'quantity' => 1,
        ]);

        $response = $this->deleteJson(
            "/api/v1/work-orders/{$this->workOrder->id}/items/{$item->id}"
        );

        $response->assertStatus(204);

        $this->assertDatabaseMissing('work_order_items', ['id' => $item->id]);
    }

    public function test_cannot_delete_item_belonging_to_other_work_order(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $item = WorkOrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $otherWorkOrder->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/work-orders/{$this->workOrder->id}/items/{$item->id}"
        );

        $response->assertStatus(403);

        $this->assertDatabaseHas('work_order_items', ['id' => $item->id]);
    }
}
