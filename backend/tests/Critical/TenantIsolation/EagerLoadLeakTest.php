<?php

/**
 * Tenant Isolation — Eager Load Leak Prevention
 *
 * Validates that eager-loaded relationships do NOT leak data across tenants.
 * Even when the parent record is correctly scoped, related records must also
 * be scoped to the same tenant.
 *
 * FAILURE HERE = RELATIONSHIP DATA LEAK (SUBTLE BUT CRITICAL)
 */

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Model::unguard();
    Model::preventLazyLoading(false);
    Gate::before(fn () => true);

    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->userA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'current_tenant_id' => $this->tenantA->id,
        'is_active' => true,
    ]);

    $this->userB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'current_tenant_id' => $this->tenantB->id,
        'is_active' => true,
    ]);

    $this->customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'EL Cust A', 'type' => 'PJ',
    ]);
    $this->customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'EL Cust B', 'type' => 'PJ',
    ]);

    $this->withoutMiddleware([
        EnsureTenantScope::class,
        CheckPermission::class,
    ]);
});

// ══════════════════════════════════════════════════════════════════
//  WORK ORDER -> CUSTOMER
// ══════════════════════════════════════════════════════════════════

test('WorkOrder eager-loaded customer belongs to same tenant', function () {
    $woA = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'EL-WO-A',
        'description' => 'EL WO A', 'status' => 'open',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $loaded = WorkOrder::with('customer')->find($woA->id);
    expect($loaded)->not->toBeNull();
    expect($loaded->customer)->not->toBeNull();
    expect($loaded->customer->tenant_id)->toBe($this->tenantA->id);
});

test('WorkOrder with items — items belong to same tenant', function () {
    $woA = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'EL-ITEM-A',
        'description' => 'Items A', 'status' => 'open',
    ]);

    WorkOrderItem::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'work_order_id' => $woA->id,
        'type' => 'service', 'description' => 'Item A1', 'quantity' => 1,
        'unit_price' => 100, 'total' => 100,
    ]);
    // Deliberately create an orphan item with wrong tenant (simulates data corruption)
    WorkOrderItem::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'work_order_id' => $woA->id,
        'type' => 'service', 'description' => 'Item B Leak', 'quantity' => 1,
        'unit_price' => 999, 'total' => 999,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $loaded = WorkOrder::with('items')->find($woA->id);
    expect($loaded)->not->toBeNull();

    // All loaded items should belong to tenant A
    $loaded->items->each(function ($item) {
        expect($item->tenant_id)->toBe($this->tenantA->id);
    });
    expect($loaded->items->pluck('description')->toArray())->not->toContain('Item B Leak');
});

// ══════════════════════════════════════════════════════════════════
//  CUSTOMER -> WORK ORDERS, QUOTES, EQUIPMENT
// ══════════════════════════════════════════════════════════════════

test('Customer eager-loaded workOrders belong to same tenant', function () {
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'EL-CWO-A',
        'description' => 'C WO A', 'status' => 'open',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $customer = Customer::with('workOrders')->find($this->customerA->id);
    expect($customer)->not->toBeNull();
    expect($customer->workOrders)->each(
        fn ($wo) => $wo->tenant_id->toBe($this->tenantA->id)
    );
});

test('Customer eager-loaded equipment belongs to same tenant', function () {
    Equipment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'code' => 'EL-CEQ-A', 'type' => 'balanca_analitica', 'brand' => 'A',
        'model' => 'A', 'serial_number' => 'SN-EL-CEQ-A', 'status' => 'active', 'is_active' => true,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $customer = Customer::with('equipments')->find($this->customerA->id);
    expect($customer)->not->toBeNull();
    expect($customer->equipments)->each(
        fn ($eq) => $eq->tenant_id->toBe($this->tenantA->id)
    );
});

test('Customer eager-loaded quotes belong to same tenant', function () {
    Quote::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'seller_id' => $this->userA->id, 'quote_number' => 'EL-ORC-A',
        'revision' => 1, 'status' => 'draft', 'valid_until' => now()->addDays(7),
        'subtotal' => 1000, 'total' => 1000,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $customer = Customer::with('quotes')->find($this->customerA->id);
    expect($customer)->not->toBeNull();
    expect($customer->quotes)->each(
        fn ($q) => $q->tenant_id->toBe($this->tenantA->id)
    );
});

// ══════════════════════════════════════════════════════════════════
//  PAYMENT -> RECEIVABLE
// ══════════════════════════════════════════════════════════════════

test('Payment eager-loaded payable belongs to same tenant', function () {
    $arA = AccountReceivable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'description' => 'EL AR A',
        'amount' => 1000, 'due_date' => now()->addDays(30), 'status' => 'pending',
    ]);

    $payA = Payment::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'payable_type' => AccountReceivable::class,
        'payable_id' => $arA->id, 'received_by' => $this->userA->id,
        'amount' => 500, 'payment_method' => 'pix', 'payment_date' => now()->format('Y-m-d'),
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $loaded = Payment::with('payable')->find($payA->id);
    expect($loaded)->not->toBeNull();
    expect($loaded->payable)->not->toBeNull();
    expect($loaded->payable->tenant_id)->toBe($this->tenantA->id);
});

// ══════════════════════════════════════════════════════════════════
//  CRM DEAL -> CUSTOMER, PIPELINE, ACTIVITIES
// ══════════════════════════════════════════════════════════════════

test('CrmDeal eager-loaded customer belongs to same tenant', function () {
    $pipeA = CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'EL Pipe A',
        'slug' => 'el-pipe-a-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);
    $stageA = CrmPipelineStage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'pipeline_id' => $pipeA->id,
        'name' => 'EL Stage A', 'sort_order' => 0, 'probability' => 50,
    ]);

    $dealA = CrmDeal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'pipeline_id' => $pipeA->id, 'stage_id' => $stageA->id,
        'title' => 'EL Deal A', 'value' => 5000, 'status' => 'open',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $loaded = CrmDeal::with(['customer', 'pipeline', 'stage'])->find($dealA->id);
    expect($loaded)->not->toBeNull();
    expect($loaded->customer->tenant_id)->toBe($this->tenantA->id);
    expect($loaded->pipeline->tenant_id)->toBe($this->tenantA->id);
    expect($loaded->stage->tenant_id)->toBe($this->tenantA->id);
});

// ══════════════════════════════════════════════════════════════════
//  COMMISSION EVENT -> WORK ORDER, USER
// ══════════════════════════════════════════════════════════════════

test('CommissionEvent eager-loaded workOrder belongs to same tenant', function () {
    $woA = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'EL-CE-WO-A',
        'description' => 'Commission WO A', 'status' => 'completed',
    ]);

    $ruleA = CommissionRule::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'user_id' => $this->userA->id,
        'name' => 'EL Rule A', 'type' => 'percentage', 'value' => 10,
        'applies_to' => 'all', 'calculation_type' => 'percent_gross',
        'applies_to_role' => 'technician', 'applies_when' => 'os_completed',
        'active' => true, 'priority' => 0,
    ]);

    $eventA = CommissionEvent::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'commission_rule_id' => $ruleA->id,
        'work_order_id' => $woA->id, 'user_id' => $this->userA->id,
        'base_amount' => 1000, 'commission_amount' => 100, 'proportion' => 1, 'status' => 'pending',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $loaded = CommissionEvent::with(['workOrder', 'rule'])->find($eventA->id);
    expect($loaded)->not->toBeNull();
    expect($loaded->workOrder->tenant_id)->toBe($this->tenantA->id);
    expect($loaded->rule->tenant_id)->toBe($this->tenantA->id);
});

// ══════════════════════════════════════════════════════════════════
//  CUSTOMER -> ACCOUNTS RECEIVABLE
// ══════════════════════════════════════════════════════════════════

test('Customer eager-loaded accountsReceivable belong to same tenant', function () {
    AccountReceivable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'description' => 'EL AR A',
        'amount' => 500, 'due_date' => now()->addDays(30), 'status' => 'pending',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $customer = Customer::with('accountsReceivable')->find($this->customerA->id);
    expect($customer)->not->toBeNull();
    expect($customer->accountsReceivable)->each(
        fn ($ar) => $ar->tenant_id->toBe($this->tenantA->id)
    );
});

// ══════════════════════════════════════════════════════════════════
//  CROSS-TENANT CUSTOMER INVISIBLE IN RELATIONS
// ══════════════════════════════════════════════════════════════════

test('cross-tenant customer not loadable even through relationship ID', function () {
    // Create a work order in tenant A that somehow references tenant B customer
    $woCorrupt = WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userA->id, 'number' => 'EL-CORRUPT',
        'description' => 'Corrupt ref', 'status' => 'open',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $loaded = WorkOrder::with('customer')->find($woCorrupt->id);
    expect($loaded)->not->toBeNull();
    // Customer should be null because tenant scope should filter it out
    expect($loaded->customer)->toBeNull();
});

test('withCount respects tenant scope for relationships', function () {
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'WC-A1',
        'description' => 'WC A1', 'status' => 'open',
    ]);
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'WC-A2',
        'description' => 'WC A2', 'status' => 'open',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $customer = Customer::withCount('workOrders')->find($this->customerA->id);
    expect($customer)->not->toBeNull();
    expect($customer->work_orders_count)->toBe(2);
});
