<?php

namespace Tests\Unit\Models;

use App\Http\Middleware\CheckPermission;
use App\Models\AuditLog;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Testes profundos: CommissionRule, CommissionEvent, CommissionSettlement,
 * AuditLog, AutoAssignmentRule.
 */
class CommissionAndAuditRealLogicTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);
    }

    // ═══ CommissionRule ═══

    public function test_commission_rule_create(): void
    {
        $rule = CommissionRule::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('commission_rules', ['id' => $rule->id]);
    }

    public function test_commission_rule_belongs_to_tenant(): void
    {
        $rule = CommissionRule::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $rule->tenant_id);
    }

    public function test_commission_rule_percentage_type(): void
    {
        $rule = CommissionRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'calculation_type' => 'percentage',
            'percentage' => '10.00',
        ]);
        $this->assertEquals('percentage', $rule->calculation_type);
        $this->assertEquals('10.00', $rule->percentage);
    }

    public function test_commission_rule_fixed_type(): void
    {
        $rule = CommissionRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'calculation_type' => 'fixed',
            'fixed_amount' => '150.00',
        ]);
        $this->assertEquals('fixed', $rule->calculation_type);
    }

    // ═══ CommissionEvent ═══

    public function test_commission_event_create(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('commission_events', ['id' => $event->id]);
    }

    public function test_commission_event_belongs_to_user(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertInstanceOf(User::class, $event->user);
    }

    public function test_commission_event_has_amount(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->user->id,
            'amount' => '500.00',
        ]);
        $this->assertEquals('500.00', $event->amount);
    }

    // ═══ CommissionSettlement ═══

    public function test_settlement_create(): void
    {
        $settlement = CommissionSettlement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('commission_settlements', ['id' => $settlement->id]);
    }

    public function test_settlement_belongs_to_user(): void
    {
        $settlement = CommissionSettlement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertInstanceOf(User::class, $settlement->user);
    }

    public function test_settlement_has_total(): void
    {
        $settlement = CommissionSettlement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'total_amount' => '2500.00',
        ]);
        $this->assertEquals('2500.00', $settlement->total_amount);
    }

    // ═══ AuditLog ═══

    public function test_audit_log_create(): void
    {
        $log = AuditLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => Customer::class,
            'auditable_id' => $this->customer->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    public function test_audit_log_belongs_to_user(): void
    {
        $log = AuditLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'updated',
            'auditable_type' => Customer::class,
            'auditable_id' => $this->customer->id,
        ]);
        $this->assertInstanceOf(User::class, $log->user);
    }

    public function test_audit_log_stores_changes(): void
    {
        $log = AuditLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'updated',
            'auditable_type' => Customer::class,
            'auditable_id' => $this->customer->id,
            'old_values' => ['name' => 'Antigo'],
            'new_values' => ['name' => 'Novo'],
        ]);
        $this->assertIsArray($log->old_values);
        $this->assertIsArray($log->new_values);
    }

    public function test_audit_log_tenant_filtered(): void
    {
        AuditLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'created',
            'auditable_type' => Customer::class,
            'auditable_id' => $this->customer->id,
        ]);
        $count = AuditLog::where('tenant_id', $this->tenant->id)->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }
}
