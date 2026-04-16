<?php

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WorkOrder;
use App\Services\StockService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Central',
        'type' => Warehouse::TYPE_FIXED,
    ]);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'cost_price' => 50.00,
    ]);

    $this->service = app(StockService::class);
});

test('manual entry creates stock movement with entry type', function () {
    $movement = $this->service->manualEntry(
        product: $this->product,
        qty: 10,
        warehouseId: $this->warehouse->id,
        unitCost: 50.00,
        notes: 'Compra inicial',
        user: $this->user,
    );

    expect($movement)->toBeInstanceOf(StockMovement::class);
    expect($movement->type)->toBe(StockMovementType::Entry);
    expect((float) $movement->quantity)->toBe(10.0);
    expect($movement->tenant_id)->toBe($this->tenant->id);
    expect($movement->product_id)->toBe($this->product->id);
});

test('manual exit creates stock movement with exit type', function () {
    // First add stock
    WarehouseStock::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $this->product->id,
        'quantity' => 20,
    ]);

    $movement = $this->service->manualExit(
        product: $this->product,
        qty: 5,
        warehouseId: $this->warehouse->id,
        notes: 'Saída para uso',
        user: $this->user,
    );

    expect($movement->type)->toBe(StockMovementType::Exit);
    expect((float) $movement->quantity)->toBe(5.0);
});

test('exit fails when insufficient stock', function () {
    WarehouseStock::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $this->product->id,
        'quantity' => 3,
    ]);

    expect(fn () => $this->service->manualExit(
        product: $this->product,
        qty: 10,
        warehouseId: $this->warehouse->id,
        user: $this->user,
    ))->toThrow(ValidationException::class);
});

test('manual return creates return movement', function () {
    $movement = $this->service->manualReturn(
        product: $this->product,
        qty: 2,
        warehouseId: $this->warehouse->id,
        notes: 'Devolução de peça',
        user: $this->user,
    );

    expect($movement->type)->toBe(StockMovementType::Return);
    expect((float) $movement->quantity)->toBe(2.0);
});

test('reserve checks available stock before creating', function () {
    WarehouseStock::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $this->product->id,
        'quantity' => 5,
    ]);

    expect(fn () => $this->service->manualReserve(
        product: $this->product,
        qty: 10,
        warehouseId: $this->warehouse->id,
        user: $this->user,
    ))->toThrow(ValidationException::class);
});

test('reserve succeeds when stock is sufficient', function () {
    WarehouseStock::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $this->product->id,
        'quantity' => 10,
    ]);

    $movement = $this->service->manualReserve(
        product: $this->product,
        qty: 5,
        warehouseId: $this->warehouse->id,
        user: $this->user,
    );

    expect($movement->type)->toBe(StockMovementType::Reserve);
    expect((float) $movement->quantity)->toBe(5.0);
});

test('adjustment allows positive and negative quantities', function () {
    WarehouseStock::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $this->product->id,
        'quantity' => 10,
    ]);

    $positiveAdj = $this->service->manualAdjustment(
        product: $this->product,
        qty: 5,
        warehouseId: $this->warehouse->id,
        notes: 'Ajuste positivo',
        user: $this->user,
    );

    expect($positiveAdj->type)->toBe(StockMovementType::Adjustment);
    expect((float) $positiveAdj->quantity)->toBe(5.0);

    $negativeAdj = $this->service->manualAdjustment(
        product: $this->product,
        qty: -3,
        warehouseId: $this->warehouse->id,
        notes: 'Ajuste negativo',
        user: $this->user,
    );

    expect((float) $negativeAdj->quantity)->toBe(-3.0);
});

test('transfer creates movement with source and target warehouses', function () {
    $targetWarehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Técnico',
        'type' => Warehouse::TYPE_TECHNICIAN,
        'user_id' => $this->user->id,
    ]);

    WarehouseStock::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $this->product->id,
        'quantity' => 15,
    ]);

    $movement = $this->service->transfer(
        product: $this->product,
        qty: 5,
        fromWarehouseId: $this->warehouse->id,
        toWarehouseId: $targetWarehouse->id,
        user: $this->user,
    );

    expect($movement->type)->toBe(StockMovementType::Transfer);
    expect($movement->warehouse_id)->toBe($this->warehouse->id);
    expect($movement->target_warehouse_id)->toBe($targetWarehouse->id);
});

test('transfer fails when source warehouse has insufficient stock', function () {
    $targetWarehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Técnico',
        'type' => Warehouse::TYPE_TECHNICIAN,
        'user_id' => $this->user->id,
    ]);

    WarehouseStock::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
    ]);

    expect(fn () => $this->service->transfer(
        product: $this->product,
        qty: 10,
        fromWarehouseId: $this->warehouse->id,
        toWarehouseId: $targetWarehouse->id,
        user: $this->user,
    ))->toThrow(ValidationException::class);
});

test('getAvailableQuantity returns sum of warehouse stock', function () {
    WarehouseStock::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $this->product->id,
        'quantity' => 25.5,
    ]);

    $available = $this->service->getAvailableQuantity($this->product, $this->warehouse->id);

    expect($available)->toBe(25.5);
});

test('getAvailableQuantity returns zero when no stock exists', function () {
    $available = $this->service->getAvailableQuantity($this->product, $this->warehouse->id);

    expect($available)->toBe(0.0);
});

test('resolves technician warehouse for work order', function () {
    $technician = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $techWarehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Armazém Técnico',
        'type' => Warehouse::TYPE_TECHNICIAN,
        'user_id' => $technician->id,
    ]);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_to' => $technician->id,
        'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'created_by' => $this->user->id,
    ]);

    $resolvedId = $this->service->resolveWarehouseIdForWorkOrder($wo);

    expect($resolvedId)->toBe($techWarehouse->id);
});

test('resolves central warehouse when no technician warehouse exists', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_to' => null,
        'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'created_by' => $this->user->id,
    ]);

    $resolvedId = $this->service->resolveWarehouseIdForWorkOrder($wo);

    expect($resolvedId)->toBe($this->warehouse->id);
});

test('kardex returns empty collection when no tenant context', function () {
    app()->forgetInstance('current_tenant_id');

    $result = $this->service->getKardex($this->product->id, $this->warehouse->id);

    expect($result)->toBeEmpty();
});
