<?php

namespace Tests\Feature;

use App\Events\FiscalNoteAuthorized;
use App\Events\WorkOrderInvoiced;
use App\Listeners\ReleaseWorkOrderOnFiscalNoteAuthorized;
use App\Models\Customer;
use App\Models\FiscalNote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ReleaseWorkOrderOnFiscalNoteAuthorizedTest extends TestCase
{
    public function test_it_updates_work_order_status_without_retriggering_invoicing_side_effects(): void
    {
        Event::fake([WorkOrderInvoiced::class]);

        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $systemUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'email' => 'fiscal-bot@example.com',
        ]);

        Config::set('services.fiscal.system_user_email', 'fiscal-bot@example.com');

        $workOrder = WorkOrder::factory()->delivered()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $systemUser->id,
            'status' => WorkOrder::STATUS_DELIVERED,
        ]);

        $note = FiscalNote::factory()->authorized()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'work_order_id' => $workOrder->id,
        ]);

        $listener = new ReleaseWorkOrderOnFiscalNoteAuthorized;
        $listener->handle(new FiscalNoteAuthorized($note));

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'status' => WorkOrder::STATUS_INVOICED,
        ]);

        $this->assertDatabaseHas('work_order_status_history', [
            'work_order_id' => $workOrder->id,
            'user_id' => $systemUser->id,
            'from_status' => WorkOrder::STATUS_DELIVERED,
            'to_status' => WorkOrder::STATUS_INVOICED,
        ]);

        Event::assertNotDispatched(WorkOrderInvoiced::class);
    }
}
