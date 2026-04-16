<?php

namespace Tests\Feature\Services;

use App\Enums\CommissionEventStatus;
use App\Http\Middleware\CheckPermission;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\CommissionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Testes profundos do CommissionService real:
 * calculateAndGenerate(), simulate(), warranty blocking, zero OS,
 * beneficiary identification (GAP-05, GAP-07), duplicate prevention.
 */
class CommissionServiceRealTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $technician;

    private User $seller;

    private Customer $customer;

    private CommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole('admin');

        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->technician->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->seller->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service = new CommissionService;

        $this->actingAs($this->admin);
    }

    // ── Warranty OS — no commission ──

    public function test_no_commission_for_warranty_os(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_warranty' => true,
            'total' => '5000.00',
            'assigned_to' => $this->technician->id,
        ]);

        $events = $this->service->calculateAndGenerate($wo);
        $this->assertEmpty($events);
    }

    // ── Zero total OS — no commission ──

    public function test_no_commission_for_zero_total_os(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_warranty' => false,
            'total' => '0.00',
            'assigned_to' => $this->technician->id,
        ]);

        $events = $this->service->calculateAndGenerate($wo);
        $this->assertEmpty($events);
    }

    // ── simulate() — no DB writes ──

    public function test_simulate_returns_empty_for_warranty(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_warranty' => true,
            'total' => '5000.00',
        ]);

        $result = $this->service->simulate($wo);
        $this->assertEmpty($result);
    }

    public function test_simulate_returns_empty_for_zero_total(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_warranty' => false,
            'total' => '0.00',
        ]);

        $result = $this->service->simulate($wo);
        $this->assertEmpty($result);
    }

    // ── With rules, generates commission ──

    public function test_generates_commission_with_percentage_rule(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_warranty' => false,
            'total' => '10000.00',
            'assigned_to' => $this->technician->id,
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Comissão Técnico 10%',
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'value' => 10,
            'percentage' => 10,
            'active' => true,
            'priority' => 1,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $events = $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);
        $this->assertNotEmpty($events);
        $this->assertEquals($this->technician->id, $events[0]->user_id);
    }

    // ── Duplicate prevention ──

    public function test_prevents_duplicate_commission(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_warranty' => false,
            'total' => '5000.00',
            'assigned_to' => $this->technician->id,
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Regra Dup',
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'value' => 5,
            'percentage' => 5,
            'active' => true,
            'priority' => 1,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        // First call — success
        $events1 = $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);
        $this->assertNotEmpty($events1);

        // Second call — should abort
        $this->expectException(HttpException::class);
        $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);
    }

    // ── GAP-07: Seller blocked when also technician ──

    public function test_seller_commission_blocked_when_also_technician(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_warranty' => false,
            'total' => '10000.00',
            'assigned_to' => $this->technician->id,
            'seller_id' => $this->technician->id, // Same person!
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Seller Rule',
            'applies_to_role' => CommissionRule::ROLE_SELLER,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'value' => 3,
            'percentage' => 3,
            'active' => true,
            'priority' => 1,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $events = $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);
        $this->assertEmpty($events);
        $this->assertDatabaseMissing('commission_events', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->technician->id,
            'status' => CommissionEventStatus::PENDING->value,
        ]);
    }

    // ── simulate() with rules returns data ──

    public function test_simulate_with_rules_returns_simulation_data(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_warranty' => false,
            'total' => '8000.00',
            'assigned_to' => $this->technician->id,
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sim Rule',
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'value' => 5,
            'percentage' => 5,
            'active' => true,
            'priority' => 1,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $result = $this->service->simulate($wo);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('user_id', $result[0]);
        $this->assertArrayHasKey('commission_amount', $result[0]);
        $this->assertArrayHasKey('rule_name', $result[0]);
        $this->assertEquals($this->technician->id, $result[0]['user_id']);
        $this->assertEquals('Sim Rule', $result[0]['rule_name']);
        $this->assertEquals('400.00', $result[0]['commission_amount']); // 5% of 8000
    }

    // ── calculateAndGenerateAnyTrigger ──

    public function test_any_trigger_tries_all_triggers(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_warranty' => false,
            'total' => '5000.00',
            'assigned_to' => $this->technician->id,
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Any Trigger Rule',
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'value' => 8,
            'percentage' => 8,
            'active' => true,
            'priority' => 1,
            'applies_when' => CommissionRule::WHEN_OS_INVOICED,
        ]);

        $events = $this->service->calculateAndGenerateAnyTrigger($wo);
        $this->assertNotEmpty($events);
    }

    // ── No assignee — no commission ──

    public function test_no_commission_without_assignee_or_technicians(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_warranty' => false,
            'total' => '5000.00',
            'assigned_to' => null,
            'seller_id' => null,
            'driver_id' => null,
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ghost Rule',
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'value' => 5,
            'percentage' => 5,
            'active' => true,
            'priority' => 1,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $events = $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);
        $this->assertEmpty($events);
    }

    // ── Driver commission ──

    public function test_driver_gets_commission_with_driver_rule(): void
    {
        $driver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $driver->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_warranty' => false,
            'total' => '10000.00',
            'assigned_to' => $this->technician->id,
            'driver_id' => $driver->id,
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Driver Rule',
            'applies_to_role' => CommissionRule::ROLE_DRIVER,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'value' => 2,
            'percentage' => 2,
            'active' => true,
            'priority' => 1,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $events = $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);
        $driverEvent = collect($events)->first(fn ($e) => $e->user_id === $driver->id);
        $this->assertNotNull($driverEvent);
    }
}
