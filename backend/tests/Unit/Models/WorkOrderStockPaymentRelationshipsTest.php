<?php

namespace Tests\Unit\Models;

use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Tests\TestCase;

class WorkOrderStockPaymentRelationshipsTest extends TestCase
{
    public function test_work_order_has_stock_movements_relationship(): void
    {
        $workOrder = new WorkOrder;
        $relation = $workOrder->stockMovements();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(StockMovement::class, $relation->getRelated());
        $this->assertSame('work_order_id', $relation->getForeignKeyName());
    }

    public function test_work_order_has_payments_relationship(): void
    {
        $workOrder = new WorkOrder;
        $relation = $workOrder->payments();

        $this->assertInstanceOf(MorphMany::class, $relation);
        $this->assertInstanceOf(Payment::class, $relation->getRelated());
        $this->assertSame('payable_type', $relation->getMorphType());
        $this->assertSame('payable_id', $relation->getForeignKeyName());
    }
}
