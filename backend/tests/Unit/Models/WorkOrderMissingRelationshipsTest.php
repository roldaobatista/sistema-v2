<?php

namespace Tests\Unit\Models;

use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderRating;
use App\Models\WorkOrderTimeLog;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WorkOrderMissingRelationshipsTest extends TestCase
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

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_ratings_returns_has_many_work_order_rating(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $relation = $workOrder->ratings();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(WorkOrderRating::class, $relation->getRelated());
    }

    public function test_time_logs_returns_has_many_work_order_time_log(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $relation = $workOrder->timeLogs();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(WorkOrderTimeLog::class, $relation->getRelated());
    }

    public function test_deals_returns_has_many_crm_deal(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $relation = $workOrder->deals();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(CrmDeal::class, $relation->getRelated());
    }
}
