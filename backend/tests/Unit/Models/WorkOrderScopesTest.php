<?php

namespace Tests\Unit\Models;

use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WorkOrderScopesTest extends TestCase
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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    private function createWo(array $overrides = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ], $overrides));
    }

    // ── scopeActive ──

    public function test_scope_active_filters_non_terminal_statuses(): void
    {
        $active = $this->createWo(['status' => WorkOrderStatus::IN_SERVICE->value]);
        $completed = $this->createWo(['status' => WorkOrderStatus::COMPLETED->value]);
        $cancelled = $this->createWo(['status' => WorkOrderStatus::CANCELLED->value]);
        $delivered = $this->createWo(['status' => WorkOrderStatus::DELIVERED->value]);
        $invoiced = $this->createWo(['status' => WorkOrderStatus::INVOICED->value]);

        $result = WorkOrder::active()->pluck('id')->all();

        $this->assertContains($active->id, $result);
        $this->assertNotContains($completed->id, $result);
        $this->assertNotContains($cancelled->id, $result);
        $this->assertNotContains($delivered->id, $result);
        $this->assertNotContains($invoiced->id, $result);
    }

    // ── scopeByStatus ──

    public function test_scope_by_status_filters_by_specific_status(): void
    {
        $open = $this->createWo(['status' => WorkOrderStatus::OPEN->value]);
        $inService = $this->createWo(['status' => WorkOrderStatus::IN_SERVICE->value]);

        $result = WorkOrder::byStatus(WorkOrderStatus::OPEN)->pluck('id')->all();

        $this->assertContains($open->id, $result);
        $this->assertNotContains($inService->id, $result);
    }

    // ── scopeByAssignee ──

    public function test_scope_by_assignee_filters_by_technician(): void
    {
        $tech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $assigned = $this->createWo(['assigned_to' => $tech->id]);
        $other = $this->createWo(['assigned_to' => $this->user->id]);

        $result = WorkOrder::byAssignee($tech->id)->pluck('id')->all();

        $this->assertContains($assigned->id, $result);
        $this->assertNotContains($other->id, $result);
    }

    // ── scopeOverdue ──

    public function test_scope_overdue_filters_past_sla_due_date(): void
    {
        $overdue = $this->createWo([
            'status' => WorkOrderStatus::IN_SERVICE->value,
            'sla_due_at' => now()->subHour(),
        ]);
        $onTime = $this->createWo([
            'status' => WorkOrderStatus::IN_SERVICE->value,
            'sla_due_at' => now()->addHour(),
        ]);
        $noSla = $this->createWo([
            'status' => WorkOrderStatus::IN_SERVICE->value,
            'sla_due_at' => null,
        ]);
        $completedOverdue = $this->createWo([
            'status' => WorkOrderStatus::COMPLETED->value,
            'sla_due_at' => now()->subHour(),
        ]);

        $result = WorkOrder::overdue()->pluck('id')->all();

        $this->assertContains($overdue->id, $result);
        $this->assertNotContains($onTime->id, $result);
        $this->assertNotContains($noSla->id, $result);
        $this->assertNotContains($completedOverdue->id, $result);
    }

    // ── scopeByPriority ──

    public function test_scope_by_priority_filters_by_priority(): void
    {
        $urgent = $this->createWo(['priority' => 'urgent']);
        $normal = $this->createWo(['priority' => 'normal']);

        $result = WorkOrder::byPriority('urgent')->pluck('id')->all();

        $this->assertContains($urgent->id, $result);
        $this->assertNotContains($normal->id, $result);
    }

    // ── scopePending ──

    public function test_scope_pending_filters_open_and_awaiting_dispatch(): void
    {
        $open = $this->createWo(['status' => WorkOrderStatus::OPEN->value]);
        $awaiting = $this->createWo(['status' => WorkOrderStatus::AWAITING_DISPATCH->value]);
        $inService = $this->createWo(['status' => WorkOrderStatus::IN_SERVICE->value]);

        $result = WorkOrder::pending()->pluck('id')->all();

        $this->assertContains($open->id, $result);
        $this->assertContains($awaiting->id, $result);
        $this->assertNotContains($inService->id, $result);
    }
}
