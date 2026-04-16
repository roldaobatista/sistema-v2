<?php

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\InvoicingService;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    Model::unguard();
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
});

// ── Work Order creation and state ──

test('work order is created with open status by default', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
    ]);

    expect($wo->status)->toBe(WorkOrder::STATUS_OPEN);
    expect($wo->tenant_id)->toBe($this->tenant->id);
});

test('work order can transition to in_progress', function () {
    $wo = WorkOrder::factory()->inProgress()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
    ]);

    expect($wo->status)->toBe(WorkOrder::STATUS_IN_PROGRESS);
    expect($wo->started_at)->not->toBeNull();
});

test('work order can transition to completed', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
    ]);

    expect($wo->status)->toBe(WorkOrder::STATUS_COMPLETED);
    expect($wo->completed_at)->not->toBeNull();
});

test('work order can be cancelled', function () {
    $wo = WorkOrder::factory()->cancelled()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
    ]);

    expect($wo->status)->toBe(WorkOrder::STATUS_CANCELLED);
});

test('work order can be assigned to a technician', function () {
    $technician = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'assigned_to' => $technician->id,
    ]);

    expect($wo->assigned_to)->toBe($technician->id);
});

test('work order total is zero by default', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
    ]);

    expect((float) $wo->total)->toBe(0.0);
});

test('work order can have seller and driver assigned', function () {
    $seller = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);
    $driver = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'seller_id' => $seller->id,
        'driver_id' => $driver->id,
    ]);

    expect($wo->seller_id)->toBe($seller->id);
    expect($wo->driver_id)->toBe($driver->id);
});

// ── InvoicingService ──

test('generates invoice from completed work order', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'total' => 5000.00,
    ]);

    WorkOrderItem::create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
        'description' => 'Calibração de balança',
        'quantity' => 1,
        'unit_price' => 5000.00,
        'total' => 5000.00,
        'type' => 'service',
    ]);

    $service = app(InvoicingService::class);
    $result = $service->generateFromWorkOrder($wo, $this->user->id);

    expect($result['invoice'])->toBeInstanceOf(Invoice::class);
    expect((float) $result['invoice']->total)->toBe(5000.00);
    expect($result['ar'])->toBeInstanceOf(AccountReceivable::class);
});

test('prevents duplicate invoice generation for same work order', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'total' => 5000.00,
    ]);

    WorkOrderItem::create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
        'description' => 'Serviço de calibração',
        'quantity' => 1,
        'unit_price' => 5000.00,
        'total' => 5000.00,
        'type' => 'service',
    ]);

    $service = app(InvoicingService::class);
    $first = $service->generateFromWorkOrder($wo, $this->user->id);
    $second = $service->generateFromWorkOrder($wo, $this->user->id);

    expect($first['invoice']->id)->toBe($second['invoice']->id);
    expect(Invoice::where('work_order_id', $wo->id)->count())->toBe(1);
});

test('generates multiple installments correctly', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'total' => 3000.00,
    ]);

    WorkOrderItem::create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
        'description' => 'Manutenção',
        'quantity' => 1,
        'unit_price' => 3000.00,
        'total' => 3000.00,
        'type' => 'service',
    ]);

    $service = app(InvoicingService::class);
    $result = $service->generateFromWorkOrder($wo, $this->user->id, 3);

    expect($result['receivables'])->toHaveCount(3);

    $totalReceivables = collect($result['receivables'])->sum('amount');
    expect(round($totalReceivables, 2))->toBe(3000.00);
});

test('installment remainder is added to first installment', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'total' => 100.00,
    ]);

    WorkOrderItem::create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
        'description' => 'Calibração',
        'quantity' => 1,
        'unit_price' => 100.00,
        'total' => 100.00,
        'type' => 'service',
    ]);

    $service = app(InvoicingService::class);
    $result = $service->generateFromWorkOrder($wo, $this->user->id, 3);

    // 100 / 3 = 33.33 each, remainder 0.01 on first
    $amounts = collect($result['receivables'])->pluck('amount')->map(fn ($v) => (float) $v);

    expect($amounts->sum())->toBe(100.00);
    expect($amounts[0])->toBeGreaterThanOrEqual($amounts[1]);
});

test('detects installment count from agreed_payment_notes 3x pattern', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'total' => 6000.00,
        'agreed_payment_notes' => 'Pagamento em 3x',
    ]);

    WorkOrderItem::create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
        'description' => 'Calibração',
        'quantity' => 1,
        'unit_price' => 6000.00,
        'total' => 6000.00,
        'type' => 'service',
    ]);

    $service = app(InvoicingService::class);
    $result = $service->generateFromWorkOrder($wo, $this->user->id);

    expect($result['receivables'])->toHaveCount(3);
});

test('detects installment count from agreed_payment_notes parcelas pattern', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'total' => 4000.00,
        'agreed_payment_notes' => '2 parcelas iguais',
    ]);

    WorkOrderItem::create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
        'description' => 'Calibração',
        'quantity' => 1,
        'unit_price' => 4000.00,
        'total' => 4000.00,
        'type' => 'service',
    ]);

    $service = app(InvoicingService::class);
    $result = $service->generateFromWorkOrder($wo, $this->user->id);

    expect($result['receivables'])->toHaveCount(2);
});

test('applies proportional discount when WO total is less than items sum', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'total' => 4500.00, // discounted from 5000
    ]);

    WorkOrderItem::create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
        'description' => 'Calibração A',
        'quantity' => 1,
        'unit_price' => 3000.00,
        'total' => 3000.00,
        'type' => 'service',
    ]);

    WorkOrderItem::create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
        'description' => 'Peça B',
        'quantity' => 1,
        'unit_price' => 2000.00,
        'total' => 2000.00,
        'type' => 'product',
    ]);

    $service = app(InvoicingService::class);
    $result = $service->generateFromWorkOrder($wo, $this->user->id);

    expect((float) $result['invoice']->total)->toBe(4500.00);
    expect((float) $result['invoice']->discount)->toBe(500.00);
});

test('work order delivered state has both completed_at and delivered_at', function () {
    $wo = WorkOrder::factory()->delivered()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
    ]);

    expect($wo->status)->toBe(WorkOrder::STATUS_DELIVERED);
    expect($wo->completed_at)->not->toBeNull();
    expect($wo->delivered_at)->not->toBeNull();
});
