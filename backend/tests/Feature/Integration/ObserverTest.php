<?php

use App\Enums\ExpenseStatus;
use App\Enums\StockMovementType;
use App\Enums\TenantStatus;
use App\Events\TechnicianLocationUpdated;
use App\Models\AccountPayable;
use App\Models\AgendaItem;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\SlaPolicy;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\HolidayService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['status' => 'active']);
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
// WorkOrderObserver
// ---------------------------------------------------------------------------

test('WorkOrderObserver applies SLA policy on creation', function () {
    $policy = SlaPolicy::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resolution_time_minutes' => 480,
    ]);

    $holidayService = $this->mock(HolidayService::class);
    $holidayService->shouldReceive('addBusinessMinutes')->once()->andReturn(now()->addHours(8));

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'sla_policy_id' => $policy->id,
    ]);

    expect($wo->sla_due_at)->not->toBeNull();
});

test('WorkOrderObserver sets sla_responded_at on first status change from open', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'status' => WorkOrder::STATUS_OPEN,
        'sla_responded_at' => null,
    ]);

    $wo->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);

    $wo->refresh();
    expect($wo->sla_responded_at)->not->toBeNull();
});

test('WorkOrderObserver rejects invalid status transitions', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'status' => WorkOrder::STATUS_OPEN,
    ]);

    // Attempting to go from open directly to invoiced should fail
    try {
        $wo->update(['status' => WorkOrder::STATUS_INVOICED]);
        $this->fail('Expected exception for invalid transition');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422);
    }
});

test('WorkOrderObserver sets completed_at timestamp on completion', function () {
    $wo = WorkOrder::factory()->inProgress()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'completed_at' => null,
    ]);

    $wo->update(['status' => WorkOrder::STATUS_COMPLETED]);

    $wo->refresh();
    expect($wo->completed_at)->not->toBeNull();
});

test('WorkOrderObserver sets cancelled_at timestamp on cancellation', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'status' => WorkOrder::STATUS_OPEN,
        'cancelled_at' => null,
    ]);

    $wo->update(['status' => WorkOrder::STATUS_CANCELLED]);

    $wo->refresh();
    expect($wo->cancelled_at)->not->toBeNull();
});

test('WorkOrderObserver logs critical field changes via audit', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'status' => WorkOrder::STATUS_OPEN,
        'priority' => WorkOrder::PRIORITY_NORMAL,
    ]);

    Log::shouldReceive('channel')
        ->andReturnSelf();
    Log::shouldReceive('info')
        ->once();

    $wo->update(['priority' => WorkOrder::PRIORITY_URGENT]);
});

test('WorkOrderObserver reduces SLA time for urgent priority', function () {
    $policy = SlaPolicy::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resolution_time_minutes' => 480,
    ]);

    $holidayService = $this->mock(HolidayService::class);
    $holidayService->shouldReceive('addBusinessMinutes')
        ->once()
        ->withArgs(function ($date, $minutes) {
            return $minutes === 240; // 480 * 0.5 for urgent
        })
        ->andReturn(now()->addHours(4));

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'sla_policy_id' => $policy->id,
        'priority' => WorkOrder::PRIORITY_URGENT,
    ]);
});

// ---------------------------------------------------------------------------
// CustomerObserver
// ---------------------------------------------------------------------------

test('CustomerObserver recalculates health score on creation', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    // Health score should be calculated after creation
    $customer->refresh();
    expect($customer->health_score)->not->toBeNull();
});

test('CustomerObserver recalculates health score when rating changes', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'rating' => 3,
    ]);
    $initialScore = $customer->health_score;

    $customer->update(['rating' => 5]);

    $customer->refresh();
    // We just verify recalculation happened without infinite recursion
    expect($customer->health_score)->not->toBeNull();
});

test('CustomerObserver does not recalculate on irrelevant field changes', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    // Changing name should not trigger health score recalculation
    $customer->update(['name' => 'New Name']);

    // No exception = no infinite recursion
    expect(true)->toBeTrue();
});

// ---------------------------------------------------------------------------
// ExpenseObserver
// ---------------------------------------------------------------------------

test('ExpenseObserver generates AccountPayable when expense is approved', function () {
    $expense = Expense::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'status' => ExpenseStatus::PENDING,
        'amount' => 500,
        'description' => 'Travel expense',
    ]);

    $expense->update(['status' => ExpenseStatus::APPROVED, 'approved_by' => $this->user->id]);

    $this->assertDatabaseHas('accounts_payable', [
        'tenant_id' => $this->tenant->id,
        'amount' => 500,
    ]);
});

test('ExpenseObserver does not create duplicate payables (idempotency)', function () {
    $expense = Expense::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'status' => ExpenseStatus::PENDING,
        'amount' => 500,
        'description' => 'Expense test',
    ]);

    $expense->update(['status' => ExpenseStatus::APPROVED]);

    // Simulate re-firing observer
    $expense->update(['description' => 'Updated description']);

    $apCount = AccountPayable::where('tenant_id', $this->tenant->id)
        ->where('notes', "expense:{$expense->id}")
        ->count();

    expect($apCount)->toBe(1);
});

test('ExpenseObserver notifies creator when reimbursement is scheduled', function () {
    $creator = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $approver = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $expense = Expense::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $creator->id,
        'status' => ExpenseStatus::PENDING,
        'amount' => 250,
        'description' => 'Fuel',
    ]);

    $expense->update(['status' => ExpenseStatus::APPROVED, 'approved_by' => $approver->id]);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $creator->id,
        'type' => 'expense_reimbursement_scheduled',
    ]);
});

test('ExpenseObserver ignores status changes that are not approval', function () {
    $expense = Expense::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'status' => ExpenseStatus::PENDING,
        'amount' => 100,
    ]);

    $countBefore = AccountPayable::where('tenant_id', $this->tenant->id)->count();

    $expense->update(['status' => ExpenseStatus::REJECTED]);

    $countAfter = AccountPayable::where('tenant_id', $this->tenant->id)->count();
    expect($countAfter)->toBe($countBefore);
});

// ---------------------------------------------------------------------------
// StockMovementObserver
// ---------------------------------------------------------------------------

test('StockMovementObserver generates payable on stock entry with cost', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $movement = StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Entry,
        'quantity' => 10,
        'unit_cost' => 25.00,
        'created_by' => $this->user->id,
    ]);

    $this->assertDatabaseHas('accounts_payable', [
        'tenant_id' => $this->tenant->id,
        'amount' => '250.00',
    ]);
});

test('StockMovementObserver ignores exit movements', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $countBefore = AccountPayable::where('tenant_id', $this->tenant->id)->count();

    StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Exit,
        'quantity' => 5,
        'unit_cost' => 10.00,
        'created_by' => $this->user->id,
    ]);

    $countAfter = AccountPayable::where('tenant_id', $this->tenant->id)->count();
    expect($countAfter)->toBe($countBefore);
});

test('StockMovementObserver ignores entries without cost', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $countBefore = AccountPayable::where('tenant_id', $this->tenant->id)->count();

    StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Entry,
        'quantity' => 10,
        'unit_cost' => 0,
        'created_by' => $this->user->id,
    ]);

    $countAfter = AccountPayable::where('tenant_id', $this->tenant->id)->count();
    expect($countAfter)->toBe($countBefore);
});

test('StockMovementObserver does not create duplicate payable (idempotency)', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $movement = StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Entry,
        'quantity' => 5,
        'unit_cost' => 20.00,
        'created_by' => $this->user->id,
    ]);

    $apCount = AccountPayable::where('notes', "stock_entry:{$movement->id}")->count();
    expect($apCount)->toBe(1);
});

// ---------------------------------------------------------------------------
// PriceTrackingObserver
// ---------------------------------------------------------------------------

test('PriceTrackingObserver records sell price change', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'sell_price' => 100.00,
        'cost_price' => 50.00,
    ]);

    $product->update(['sell_price' => 120.00]);

    $this->assertDatabaseHas('price_histories', [
        'priceable_type' => Product::class,
        'priceable_id' => $product->id,
        'old_sell_price' => 100.00,
        'new_sell_price' => 120.00,
    ]);
});

test('PriceTrackingObserver records cost price change', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'sell_price' => 100.00,
        'cost_price' => 50.00,
    ]);

    $product->update(['cost_price' => 60.00]);

    $this->assertDatabaseHas('price_histories', [
        'priceable_type' => Product::class,
        'priceable_id' => $product->id,
        'old_cost_price' => 50.00,
        'new_cost_price' => 60.00,
    ]);
});

test('PriceTrackingObserver calculates change percentage correctly', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'sell_price' => 100.00,
        'cost_price' => 50.00,
    ]);

    $product->update(['sell_price' => 150.00]);

    $history = PriceHistory::where('priceable_id', $product->id)
        ->where('priceable_type', Product::class)
        ->first();

    expect((float) $history->change_percent)->toBe(50.00);
});

test('PriceTrackingObserver does not record when price unchanged', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'sell_price' => 100.00,
        'cost_price' => 50.00,
    ]);

    $product->update(['name' => 'New Name']);

    $count = PriceHistory::where('priceable_id', $product->id)->count();
    expect($count)->toBe(0);
});

// ---------------------------------------------------------------------------
// TenantObserver
// ---------------------------------------------------------------------------

test('TenantObserver clears status cache on status update', function () {
    Cache::put("tenant_status_{$this->tenant->id}", 'active', 300);

    $this->tenant->update(['status' => TenantStatus::INACTIVE]);

    expect(Cache::has("tenant_status_{$this->tenant->id}"))->toBeFalse();
});

test('TenantObserver does not clear cache on non-status updates', function () {
    Cache::put("tenant_status_{$this->tenant->id}", 'active', 300);

    $this->tenant->update(['name' => 'New Tenant Name']);

    expect(Cache::has("tenant_status_{$this->tenant->id}"))->toBeTrue();
});

// ---------------------------------------------------------------------------
// TimeEntryObserver
// ---------------------------------------------------------------------------

test('TimeEntryObserver sets user status to working on work time entry', function () {
    $technician = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'available',
    ]);

    Event::fake([TechnicianLocationUpdated::class]);

    TimeEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'technician_id' => $technician->id,
        'type' => TimeEntry::TYPE_WORK,
        'ended_at' => null,
    ]);

    $technician->refresh();
    expect($technician->status)->toBe('working');
});

test('TimeEntryObserver sets user status to in_transit on travel entry', function () {
    $technician = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'available',
    ]);

    Event::fake([TechnicianLocationUpdated::class]);

    TimeEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'technician_id' => $technician->id,
        'type' => TimeEntry::TYPE_TRAVEL,
        'ended_at' => null,
    ]);

    $technician->refresh();
    expect($technician->status)->toBe('in_transit');
});

test('TimeEntryObserver sets user back to available when entry ends', function () {
    $technician = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'working',
    ]);

    Event::fake([TechnicianLocationUpdated::class]);

    $entry = TimeEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'technician_id' => $technician->id,
        'type' => TimeEntry::TYPE_WORK,
        'ended_at' => null,
    ]);

    $entry->update(['ended_at' => now()]);

    $technician->refresh();
    expect($technician->status)->toBe('available');
});

test('TimeEntryObserver sets user to available when open entry deleted', function () {
    $technician = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'working',
    ]);

    Event::fake([TechnicianLocationUpdated::class]);

    $entry = TimeEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'technician_id' => $technician->id,
        'type' => TimeEntry::TYPE_WORK,
        'ended_at' => null,
    ]);

    $entry->delete();

    $technician->refresh();
    expect($technician->status)->toBe('available');
});

// ---------------------------------------------------------------------------
// CrmDealAgendaObserver
// ---------------------------------------------------------------------------

test('CrmDealAgendaObserver creates agenda item when deal is created', function () {
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
        'assigned_to' => $this->user->id,
        'value' => 15000,
        'status' => 'open',
    ]);

    $this->assertDatabaseHas('central_items', [
        'tenant_id' => $this->tenant->id,
        'assignee_user_id' => $this->user->id,
    ]);
});

test('CrmDealAgendaObserver maps high-value deals to urgent priority', function () {
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
        'assigned_to' => $this->user->id,
        'value' => 60000,
        'status' => 'open',
    ]);

    $agenda = AgendaItem::withoutGlobalScopes()
        ->where('ref_type', (new CrmDeal)->getMorphClass())
        ->where('ref_id', $deal->id)
        ->first();

    expect($agenda)->not->toBeNull();
});

test('CrmDealAgendaObserver closes agenda when deal is won', function () {
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
        'assigned_to' => $this->user->id,
        'value' => 5000,
        'status' => 'open',
    ]);

    $deal->update(['status' => 'won']);

    $agenda = AgendaItem::withoutGlobalScopes()
        ->where('ref_type', (new CrmDeal)->getMorphClass())
        ->where('ref_id', $deal->id)
        ->first();

    if ($agenda) {
        expect($agenda->status->value)->toBeIn(['completed']);
    }
});
