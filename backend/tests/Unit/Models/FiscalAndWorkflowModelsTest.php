<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\ExpenseCategory;
use App\Models\FiscalNote;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Testes profundos: FiscalNote, WorkOrderItem, Notification,
 * ExpenseCategory, Equipment lifecycle.
 */
class FiscalAndWorkflowModelsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);
    }

    // ═══ FiscalNote ═══

    public function test_fiscal_note_create(): void
    {
        $note = FiscalNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertDatabaseHas('fiscal_notes', ['id' => $note->id]);
    }

    public function test_fiscal_note_belongs_to_customer(): void
    {
        $note = FiscalNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertInstanceOf(Customer::class, $note->customer);
    }

    public function test_fiscal_note_soft_deletes(): void
    {
        $note = FiscalNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $note->delete();
        $this->assertSoftDeleted($note);
    }

    // ═══ WorkOrderItem ═══

    public function test_wo_item_create(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $item = WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'description' => 'Calibração de balança',
            'quantity' => 2,
            'unit_price' => '500.00',
        ]);
        $this->assertDatabaseHas('work_order_items', ['id' => $item->id]);
    }

    public function test_wo_item_belongs_to_wo(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $item = WorkOrderItem::factory()->create(['work_order_id' => $wo->id]);
        $this->assertInstanceOf(WorkOrder::class, $item->workOrder);
    }

    public function test_wo_item_total_calculation(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $item = WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'quantity' => 3,
            'unit_price' => '200.00',
        ]);
        $total = bcmul($item->quantity, $item->unit_price, 2);
        $this->assertEquals('600.00', $total);
    }

    // ═══ Notification ═══

    public function test_notification_create(): void
    {
        $notif = Notification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('notifications', ['id' => $notif->id]);
    }

    public function test_notification_belongs_to_user(): void
    {
        $notif = Notification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertInstanceOf(User::class, $notif->user);
    }

    public function test_notification_mark_as_read(): void
    {
        $notif = Notification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'read_at' => null,
        ]);
        $notif->update(['read_at' => now()]);
        $this->assertNotNull($notif->fresh()->read_at);
    }

    // ═══ ExpenseCategory ═══

    public function test_expense_category_create(): void
    {
        $cat = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('expense_categories', ['id' => $cat->id]);
    }

    public function test_expense_category_has_expenses(): void
    {
        $cat = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertCount(0, $cat->expenses);
    }

    // ═══ Equipment lifecycle ═══

    public function test_equipment_create_with_all_calibration_fields(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'accuracy_class' => 'III',
            'min_capacity' => '0.00',
            'max_capacity' => '30000.00',
            'resolution' => '1.00',
            'calibration_interval_months' => 12,
        ]);
        $this->assertEquals('III', $eq->accuracy_class);
        $this->assertEquals(12, $eq->calibration_interval_months);
    }

    public function test_equipment_belongs_to_customer(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertInstanceOf(Customer::class, $eq->customer);
    }

    public function test_equipment_soft_deletes(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $eq->delete();
        $this->assertSoftDeleted($eq);
    }

    public function test_equipment_has_work_orders(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertTrue(method_exists($eq, 'workOrders') || method_exists($eq, 'work_orders'));
    }
}
