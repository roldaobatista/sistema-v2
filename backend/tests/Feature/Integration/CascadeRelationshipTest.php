<?php

use App\Enums\StockMovementType;
use App\Models\AccountReceivable;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\RecurringContract;
use App\Models\ServiceCall;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Models\WorkOrderStatusHistory;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    app()->instance('current_tenant_id', $this->tenant->id);
    Gate::before(fn () => true);
    Sanctum::actingAs($this->user, ['*']);
});

// ---------------------------------------------------------------------------
// Customer Cascade Relationships
// ---------------------------------------------------------------------------

test('customer has many work orders', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo1 = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);
    $wo2 = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    expect($customer->workOrders()->count())->toBe(2);
});

test('customer has many quotes', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    Quote::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
    ]);

    expect($customer->quotes()->count())->toBe(3);
});

test('customer has many accounts receivable', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    AccountReceivable::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);

    expect($customer->accountsReceivable()->count())->toBe(2);
});

test('customer has many equipment', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    Equipment::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);

    expect($customer->equipments()->count())->toBe(2);
});

test('customer has many service calls', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    ServiceCall::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);

    expect($customer->serviceCalls()->count())->toBe(2);
});

test('customer has many crm deals', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    CrmDeal::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    expect($customer->deals()->count())->toBe(2);
});

test('customer has many recurring contracts', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    RecurringContract::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);

    expect($customer->recurringContracts()->count())->toBe(2);
});

test('soft deleting customer preserves related work orders', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $customer->delete();

    $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    $this->assertDatabaseHas('work_orders', [
        'id' => $wo->id,
        'customer_id' => $customer->id,
        'deleted_at' => null,
    ]);
});

test('customer with CRM activities maintains relationship', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    CrmActivity::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'user_id' => $this->user->id,
        'type' => 'follow_up',
        'title' => 'Test activity',
        'scheduled_at' => now(),
    ]);

    expect($customer->activities()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// WorkOrder Cascade Relationships
// ---------------------------------------------------------------------------

test('work order has many items', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    WorkOrderItem::factory()->count(3)->create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
    ]);

    expect($wo->items()->count())->toBe(3);
});

test('work order has many status history entries', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    WorkOrderStatusHistory::factory()->count(2)->create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    expect($wo->statusHistory()->count())->toBe(2);
});

test('work order belongs to customer', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    expect($wo->customer->id)->toBe($customer->id);
});

test('work order belongs to assignee user', function () {
    $technician = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_to' => $technician->id,
        'created_by' => $this->user->id,
    ]);

    expect($wo->assignee->id)->toBe($technician->id);
});

test('work order belongs to quote', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
    ]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'quote_id' => $quote->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    expect($wo->quote->id)->toBe($quote->id);
});

test('work order belongs to equipment', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    expect($wo->equipment->id)->toBe($equipment->id);
});

test('soft deleting work order preserves items', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $item = WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $wo->delete();

    $this->assertSoftDeleted('work_orders', ['id' => $wo->id]);
    $this->assertDatabaseHas('work_order_items', ['id' => $item->id]);
});

test('work order can have parent-child relationship', function () {
    $parent = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $child = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'parent_id' => $parent->id,
        'created_by' => $this->user->id,
    ]);

    expect($parent->children()->count())->toBe(1);
    expect($child->parent->id)->toBe($parent->id);
});

// ---------------------------------------------------------------------------
// Quote Cascade Relationships
// ---------------------------------------------------------------------------

test('quote has many equipments with items', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
    ]);
    QuoteEquipment::factory()->count(2)->create([
        'quote_id' => $quote->id,
        'tenant_id' => $this->tenant->id,
    ]);

    expect($quote->equipments()->count())->toBe(2);
});

test('quote has many work orders', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
    ]);
    WorkOrder::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'quote_id' => $quote->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    expect($quote->workOrders()->count())->toBe(2);
});

test('quote belongs to seller', function () {
    $seller = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $seller->id,
    ]);

    expect($quote->seller->id)->toBe($seller->id);
});

test('soft deleting quote preserves related work orders', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
    ]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'quote_id' => $quote->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $quote->delete();

    $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
    $this->assertDatabaseHas('work_orders', [
        'id' => $wo->id,
        'deleted_at' => null,
    ]);
});

// ---------------------------------------------------------------------------
// Equipment Cascade Relationships
// ---------------------------------------------------------------------------

test('equipment has many calibrations', function () {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    EquipmentCalibration::create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'calibration_date' => now(),
        'performed_by' => $this->user->id,
        'result' => 'approved',
    ]);

    expect($equipment->calibrations()->count())->toBe(1);
});

test('equipment belongs to customer', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);

    expect($equipment->customer->id)->toBe($customer->id);
});

test('equipment has many work orders', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);
    WorkOrder::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    expect($equipment->workOrders()->count())->toBe(2);
});

test('soft deleting equipment preserves calibration history', function () {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    EquipmentCalibration::create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'calibration_date' => now(),
        'performed_by' => $this->user->id,
        'result' => 'approved',
    ]);

    $equipment->delete();

    $this->assertSoftDeleted('equipments', ['id' => $equipment->id]);
    $this->assertDatabaseHas('equipment_calibrations', [
        'equipment_id' => $equipment->id,
    ]);
});

// ---------------------------------------------------------------------------
// AccountReceivable Cascade Relationships
// ---------------------------------------------------------------------------

test('account receivable has many payments', function () {
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    Payment::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'payable_type' => AccountReceivable::class,
        'payable_id' => $ar->id,
    ]);

    expect($ar->payments()->count())->toBe(2);
});

test('account receivable belongs to customer', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);

    expect($ar->customer->id)->toBe($customer->id);
});

test('account receivable belongs to work order', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $wo->id,
    ]);

    expect($ar->workOrder->id)->toBe($wo->id);
});

// ---------------------------------------------------------------------------
// Product Cascade Relationships
// ---------------------------------------------------------------------------

test('product has many stock movements', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
    StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Entry,
        'quantity' => 10,
        'created_by' => $this->user->id,
    ]);

    expect($product->stockMovements()->count())->toBe(1);
});

test('soft deleting product preserves stock movements', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
    $movement = StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Entry,
        'quantity' => 5,
        'created_by' => $this->user->id,
    ]);

    $product->delete();

    $this->assertSoftDeleted('products', ['id' => $product->id]);
    $this->assertDatabaseHas('stock_movements', ['id' => $movement->id]);
});

// ---------------------------------------------------------------------------
// CRM Deal Cascade Relationships
// ---------------------------------------------------------------------------

test('crm deal belongs to customer', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    expect($deal->customer->id)->toBe($customer->id);
});

test('crm deal belongs to pipeline and stage', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    expect($deal->pipeline->id)->toBe($pipeline->id);
    expect($deal->stage->id)->toBe($stage->id);
});

// ---------------------------------------------------------------------------
// Tenant Cascade (data isolation)
// ---------------------------------------------------------------------------

test('tenant has many users', function () {
    User::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    // Count includes the default user from beforeEach
    expect(User::where('tenant_id', $this->tenant->id)->count())->toBeGreaterThanOrEqual(3);
});

test('tenant has many customers', function () {
    Customer::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    expect(Customer::where('tenant_id', $this->tenant->id)->count())->toBe(3);
});

test('tenant has many work orders', function () {
    WorkOrder::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    expect(WorkOrder::where('tenant_id', $this->tenant->id)->count())->toBe(3);
});

test('data from one tenant is isolated from another', function () {
    $tenant2 = Tenant::factory()->create();
    $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);

    $customer1 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $customer2 = Customer::factory()->create(['tenant_id' => $tenant2->id]);

    app()->instance('current_tenant_id', $this->tenant->id);
    $visibleCustomers = Customer::all();

    expect($visibleCustomers->pluck('id')->toArray())->toContain($customer1->id);
    expect($visibleCustomers->pluck('id')->toArray())->not->toContain($customer2->id);
});

test('work orders from different tenants are isolated', function () {
    $tenant2 = Tenant::factory()->create();
    $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);

    $wo1 = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $wo2 = WorkOrder::factory()->create([
        'tenant_id' => $tenant2->id,
        'created_by' => $user2->id,
    ]);

    app()->instance('current_tenant_id', $this->tenant->id);
    $visibleWos = WorkOrder::all();

    expect($visibleWos->pluck('id')->toArray())->toContain($wo1->id);
    expect($visibleWos->pluck('id')->toArray())->not->toContain($wo2->id);
});

// ---------------------------------------------------------------------------
// Cross-model relationships
// ---------------------------------------------------------------------------

test('work order item references a product', function () {
    // Create a product with track_stock=false to avoid StockService validation
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'track_stock' => false,
    ]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $item = WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
        'type' => WorkOrderItem::TYPE_PRODUCT,
        'reference_id' => $product->id,
    ]);

    expect($item->reference_id)->toBe($product->id);
    expect($wo->items()->where('type', WorkOrderItem::TYPE_PRODUCT)->count())->toBe(1);
});

test('invoice belongs to work order', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $wo->id,
    ]);

    expect($invoice->workOrder->id)->toBe($wo->id);
});

test('service call can belong to quote', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
    ]);
    $sc = ServiceCall::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'quote_id' => $quote->id,
    ]);

    expect($sc->quote_id)->toBe($quote->id);
});
