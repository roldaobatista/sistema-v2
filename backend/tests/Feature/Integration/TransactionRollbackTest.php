<?php

use App\Enums\ExpenseStatus;
use App\Enums\QuoteStatus;
use App\Enums\StockMovementType;
use App\Events\WorkOrderCancelled;
use App\Events\WorkOrderInvoiced;
use App\Listeners\HandleWorkOrderCancellation;
use App\Listeners\HandleWorkOrderInvoicing;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\CommissionService;
use App\Services\InvoicingService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
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
// Work Order creation with items
// ---------------------------------------------------------------------------

test('failed item creation rolls back work order creation in transaction', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $woBefore = WorkOrder::count();

    try {
        DB::transaction(function () use ($customer) {
            $wo = WorkOrder::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'created_by' => $this->user->id,
            ]);

            // Simulate item creation failure
            throw new Exception('Failed to create work order item');
        });
    } catch (Exception) {
        // Expected
    }

    expect(WorkOrder::count())->toBe($woBefore);
});

test('successful work order with items creates all records', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'track_stock' => false]);

    $result = DB::transaction(function () use ($customer, $product) {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $item = WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'type' => WorkOrderItem::TYPE_PRODUCT,
            'reference_id' => $product->id,
        ]);

        return ['wo' => $wo, 'item' => $item];
    });

    $this->assertDatabaseHas('work_orders', ['id' => $result['wo']->id]);
    $this->assertDatabaseHas('work_order_items', ['id' => $result['item']->id]);
});

// ---------------------------------------------------------------------------
// Payment with receivable update
// ---------------------------------------------------------------------------

test('failed payment creation does not update receivable status', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'amount' => 1000,
        'amount_paid' => 0,
        'status' => AccountReceivable::STATUS_PENDING,
    ]);

    try {
        DB::transaction(function () use ($ar) {
            $ar->update([
                'amount_paid' => 1000,
                'status' => AccountReceivable::STATUS_PAID,
            ]);

            // Simulate payment creation failure
            throw new Exception('Payment gateway error');
        });
    } catch (Exception) {
        // Expected
    }

    $ar->refresh();
    expect($ar->status->value)->toBe(AccountReceivable::STATUS_PENDING);
    expect((float) $ar->amount_paid)->toBe(0.0);
});

test('successful payment updates receivable correctly', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'amount' => 500,
        'amount_paid' => 0,
        'status' => AccountReceivable::STATUS_PENDING,
    ]);

    DB::transaction(function () use ($ar) {
        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $ar->id,
            'amount' => 500,
        ]);

        $ar->update([
            'amount_paid' => 500,
            'status' => AccountReceivable::STATUS_PAID,
        ]);
    });

    $ar->refresh();
    expect($ar->status->value)->toBe(AccountReceivable::STATUS_PAID);
    expect((float) $ar->amount_paid)->toBe(500.0);
});

// ---------------------------------------------------------------------------
// Quote approval with OS generation
// ---------------------------------------------------------------------------

test('failed work order generation keeps quote as draft', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
        'status' => QuoteStatus::DRAFT,
    ]);

    try {
        DB::transaction(function () use ($quote) {
            $quote->update(['status' => QuoteStatus::APPROVED, 'approved_at' => now()]);

            // Simulate OS generation failure
            throw new Exception('Failed to generate work order from quote');
        });
    } catch (Exception) {
        // Expected
    }

    $quote->refresh();
    expect($quote->status)->toBe(QuoteStatus::DRAFT);
});

test('successful quote approval creates work order in transaction', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
        'status' => QuoteStatus::DRAFT,
    ]);

    $result = DB::transaction(function () use ($quote, $customer) {
        $quote->update(['status' => QuoteStatus::APPROVED, 'approved_at' => now()]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'quote_id' => $quote->id,
            'created_by' => $this->user->id,
        ]);

        return ['quote' => $quote, 'wo' => $wo];
    });

    $this->assertDatabaseHas('work_orders', [
        'id' => $result['wo']->id,
        'quote_id' => $quote->id,
    ]);
});

// ---------------------------------------------------------------------------
// Stock transfer atomicity
// ---------------------------------------------------------------------------

test('failed stock destination entry does not deduct from source', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    $movementsBefore = StockMovement::where('product_id', $product->id)->count();

    try {
        DB::transaction(function () use ($product, $warehouse) {
            // Deduct from source warehouse
            StockMovement::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => StockMovementType::Exit,
                'quantity' => 10,
                'created_by' => $this->user->id,
                'reference' => 'transfer-out',
            ]);

            // Simulate failure on destination entry
            throw new Exception('Destination warehouse locked');
        });
    } catch (Exception) {
        // Expected
    }

    $movementsAfter = StockMovement::where('product_id', $product->id)->count();
    expect($movementsAfter)->toBe($movementsBefore);
});

test('successful stock transfer creates both movements', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

    DB::transaction(function () use ($product, $warehouse) {
        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovementType::Exit,
            'quantity' => 5,
            'created_by' => $this->user->id,
            'reference' => 'transfer-out',
        ]);

        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovementType::Entry,
            'quantity' => 5,
            'created_by' => $this->user->id,
            'reference' => 'transfer-in',
        ]);
    });

    $exits = StockMovement::where('product_id', $product->id)
        ->where('reference', 'transfer-out')
        ->count();
    $entries = StockMovement::where('product_id', $product->id)
        ->where('reference', 'transfer-in')
        ->count();

    expect($exits)->toBe(1);
    expect($entries)->toBe(1);
});

// ---------------------------------------------------------------------------
// Invoicing rollback
// ---------------------------------------------------------------------------

test('HandleWorkOrderInvoicing rolls back on stock deduction failure', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'track_stock' => false]);
    $wo = WorkOrder::factory()->delivered()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'total' => 1000,
    ]);
    WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
        'type' => WorkOrderItem::TYPE_PRODUCT,
        'reference_id' => $product->id,
        'quantity' => 5,
    ]);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $wo->id,
    ]);

    $invoicingService = $this->mock(InvoicingService::class);
    $invoicingService->shouldReceive('generateFromWorkOrder')
        ->once()
        ->andReturn(['invoice' => $invoice, 'ar' => null, 'receivables' => []]);

    $stockService = $this->mock(StockService::class);
    $stockService->shouldReceive('deduct')
        ->andThrow(new Exception('Insufficient stock'));
    $stockService->shouldReceive('resolveWarehouseIdForWorkOrder')
        ->andReturn(null);

    $this->mock(CommissionService::class)->shouldIgnoreMissing();

    $event = new WorkOrderInvoiced($wo, $this->user, 'delivered');
    $listener = app(HandleWorkOrderInvoicing::class);

    try {
        $listener->handle($event);
    } catch (Exception) {
        // Expected - stock deduction failure
    }

    // Invoice should be cancelled due to markInvoicingAsFailed
    $invoice->refresh();
    expect($invoice->status->value)->toBe(Invoice::STATUS_CANCELLED);
});

// ---------------------------------------------------------------------------
// Commission reversal on cancellation
// ---------------------------------------------------------------------------

test('commission reversal in cancellation is atomic', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);
    $commission = CommissionEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $wo->id,
        'user_id' => $this->user->id,
        'status' => CommissionEvent::STATUS_PENDING,
    ]);

    $this->mock(StockService::class)->shouldIgnoreMissing();

    $event = new WorkOrderCancelled($wo, $this->user, 'Cancelled by test', 'open');
    $listener = app(HandleWorkOrderCancellation::class);
    $listener->handle($event);

    $commission->refresh();
    expect($commission->status->value)->toBe(CommissionEvent::STATUS_REVERSED);
});

// ---------------------------------------------------------------------------
// Multiple partial payments
// ---------------------------------------------------------------------------

test('partial payments accumulate correctly within transaction', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'amount' => 1000,
        'amount_paid' => 0,
        'status' => AccountReceivable::STATUS_PENDING,
    ]);

    DB::transaction(function () use ($ar) {
        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $ar->id,
            'amount' => 400,
        ]);
        // Payment::created event auto-increments amount_paid on the payable
    });

    DB::transaction(function () use ($ar) {
        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $ar->id,
            'amount' => 600,
        ]);
        // Payment::created event auto-increments amount_paid and recalculateStatus marks as paid
    });

    $ar->refresh();
    expect((float) $ar->amount_paid)->toBe(1000.0);
    expect($ar->status->value)->toBe(AccountReceivable::STATUS_PAID);
});

// ---------------------------------------------------------------------------
// Nested transaction rollback
// ---------------------------------------------------------------------------

test('nested transaction failure rolls back outer transaction', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $woBefore = WorkOrder::count();
    $arBefore = AccountReceivable::count();

    try {
        DB::transaction(function () use ($customer) {
            $wo = WorkOrder::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'created_by' => $this->user->id,
            ]);

            DB::transaction(function () use ($wo) {
                AccountReceivable::factory()->create([
                    'tenant_id' => $this->tenant->id,
                    'work_order_id' => $wo->id,
                ]);

                throw new Exception('Inner transaction failed');
            });
        });
    } catch (Exception) {
        // Expected
    }

    expect(WorkOrder::count())->toBe($woBefore);
    expect(AccountReceivable::count())->toBe($arBefore);
});

// ---------------------------------------------------------------------------
// Concurrent update safety
// ---------------------------------------------------------------------------

test('accounts receivable amount_paid uses increment for concurrency safety', function () {
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'amount' => 1000,
        'amount_paid' => 0,
        'status' => AccountReceivable::STATUS_PENDING,
    ]);

    // Simulate two concurrent partial payments using increment
    $ar->increment('amount_paid', 300);
    $ar->increment('amount_paid', 200);

    $ar->refresh();
    expect((float) $ar->amount_paid)->toBe(500.0);
});

// ---------------------------------------------------------------------------
// Expense approval with payable generation
// ---------------------------------------------------------------------------

test('expense approval generates payable atomically', function () {
    $expense = Expense::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'status' => ExpenseStatus::PENDING,
        'amount' => 300,
        'description' => 'Test expense',
    ]);

    $apBefore = AccountPayable::count();

    DB::transaction(function () use ($expense) {
        $expense->update([
            'status' => ExpenseStatus::APPROVED,
            'approved_by' => $this->user->id,
        ]);
    });

    $apAfter = AccountPayable::count();
    expect($apAfter)->toBeGreaterThan($apBefore);
});

// ---------------------------------------------------------------------------
// Renegotiation (cancelling old receivable, creating new ones)
// ---------------------------------------------------------------------------

test('renegotiation rolls back if new receivable creation fails', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $oldAr = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'amount' => 5000,
        'status' => AccountReceivable::STATUS_PENDING,
    ]);

    try {
        DB::transaction(function () use ($oldAr, $customer) {
            $oldAr->update(['status' => AccountReceivable::STATUS_CANCELLED]);

            // Create first new installment
            AccountReceivable::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'amount' => 2500,
                'status' => AccountReceivable::STATUS_PENDING,
            ]);

            // Simulate failure on second installment
            throw new Exception('Failed to create second installment');
        });
    } catch (Exception) {
        // Expected
    }

    $oldAr->refresh();
    expect($oldAr->status->value)->toBe(AccountReceivable::STATUS_PENDING);
});
