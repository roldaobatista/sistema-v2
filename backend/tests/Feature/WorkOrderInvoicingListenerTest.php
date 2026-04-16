<?php

namespace Tests\Feature;

use App\Events\WorkOrderInvoiced;
use App\Listeners\HandleWorkOrderInvoicing;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\CommissionService;
use App\Services\InvoicingService;
use App\Services\StockService;
use RuntimeException;
use Tests\TestCase;

class WorkOrderInvoicingListenerTest extends TestCase
{
    public function test_it_reverts_work_order_and_cancels_financial_records_when_stock_deduction_fails(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        app()->instance('current_tenant_id', $tenant->id);

        $workOrder = WorkOrder::factory()->delivered()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'status' => WorkOrder::STATUS_INVOICED,
            'total' => 450,
        ]);

        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'track_stock' => true,
        ]);

        WorkOrderItem::withoutEvents(function () use ($tenant, $workOrder, $product): void {
            WorkOrderItem::create([
                'tenant_id' => $tenant->id,
                'work_order_id' => $workOrder->id,
                'type' => WorkOrderItem::TYPE_PRODUCT,
                'reference_id' => $product->id,
                'description' => $product->name,
                'quantity' => 2,
                'unit_price' => 225,
                'cost_price' => 100,
                'discount' => 0,
                'total' => 450,
            ]);
        });

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'work_order_id' => $workOrder->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'status' => Invoice::STATUS_ISSUED,
            'fiscal_status' => null,
            'fiscal_error' => null,
        ]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'work_order_id' => $workOrder->id,
            'invoice_id' => $invoice->id,
            'created_by' => $user->id,
            'status' => AccountReceivable::STATUS_PENDING,
            'amount' => 450,
            'amount_paid' => 0,
        ]);

        $invoicingService = $this->mock(InvoicingService::class);
        $stockService = $this->mock(StockService::class);
        $commissionService = $this->mock(CommissionService::class);

        $invoicingService
            ->shouldReceive('generateFromWorkOrder')
            ->once()
            ->withArgs(fn (WorkOrder $wo, ?int $userId) => $wo->id === $workOrder->id && $userId === $user->id)
            ->andReturn([
                'invoice' => $invoice,
                'ar' => $receivable,
                'receivables' => [$receivable],
            ]);

        $stockService
            ->shouldReceive('resolveWarehouseIdForWorkOrder')
            ->once()
            ->andReturn(1);

        $stockService
            ->shouldReceive('deduct')
            ->once()
            ->andThrow(new RuntimeException('Saldo insuficiente no armazem central.'));

        $commissionService->shouldNotReceive('calculateAndGenerate');

        $listener = new HandleWorkOrderInvoicing($invoicingService, $stockService, $commissionService);

        try {
            $listener->handle(new WorkOrderInvoiced($workOrder, $user, WorkOrder::STATUS_DELIVERED));
            $this->fail('A excecao de falha de estoque deveria ser relancada pelo listener.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Saldo insuficiente no armazem central.', $exception->getMessage());
        }

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'status' => WorkOrder::STATUS_DELIVERED,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_CANCELLED,
            'fiscal_status' => Invoice::FISCAL_STATUS_FAILED,
        ]);

        $this->assertSoftDeleted('accounts_receivable', [
            'id' => $receivable->id,
            'status' => AccountReceivable::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('work_order_status_history', [
            'work_order_id' => $workOrder->id,
            'from_status' => WorkOrder::STATUS_INVOICED,
            'to_status' => WorkOrder::STATUS_DELIVERED,
        ]);
    }
}
