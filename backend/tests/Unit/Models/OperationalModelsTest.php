<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GeofenceLocation;
use App\Models\Invoice;
use App\Models\QuickNote;
use App\Models\ReconciliationRule;
use App\Models\Schedule;
use App\Models\TechnicianCashFund;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitCheckin;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OperationalModelsTest extends TestCase
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

    // ── Expense ──

    public function test_expense_belongs_to_creator(): void
    {
        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $expense->creator);
    }

    public function test_expense_belongs_to_category(): void
    {
        $cat = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'expense_category_id' => $cat->id,
        ]);

        $this->assertInstanceOf(ExpenseCategory::class, $expense->category);
    }

    public function test_expense_soft_delete(): void
    {
        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $expense->delete();
        $this->assertNull(Expense::find($expense->id));
        $this->assertNotNull(Expense::withTrashed()->find($expense->id));
    }

    public function test_expense_decimal_casts(): void
    {
        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => '345.67',
        ]);

        $expense->refresh();
        $this->assertEquals('345.67', $expense->amount);
    }

    // ── Schedule ──

    public function test_schedule_belongs_to_technician(): void
    {
        $schedule = Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $schedule->technician);
    }

    public function test_schedule_fillable_fields(): void
    {
        $schedule = new Schedule;
        $this->assertContains('tenant_id', $schedule->getFillable());
        $this->assertContains('technician_id', $schedule->getFillable());
    }

    // ── TechnicianCashFund ──

    public function test_technician_cash_fund_belongs_to_user(): void
    {
        $fund = TechnicianCashFund::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'balance' => '500.00',
        ]);

        $this->assertInstanceOf(User::class, $fund->technician);
    }

    public function test_technician_cash_fund_has_many_transactions(): void
    {
        $fund = TechnicianCashFund::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'balance' => '500.00',
        ]);

        $this->assertInstanceOf(HasMany::class, $fund->transactions());
    }

    // ── Invoice ──

    public function test_invoice_belongs_to_work_order(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'NF-000001',
            'total' => '1500.00',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(WorkOrder::class, $invoice->workOrder);
    }

    public function test_invoice_belongs_to_customer(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'NF-000001',
            'total' => '1500.00',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Customer::class, $invoice->customer);
    }

    // ── QuickNote ──

    public function test_quick_note_belongs_to_user(): void
    {
        $note = QuickNote::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'content' => 'Nota rápida de teste',
        ]);

        $this->assertInstanceOf(User::class, $note->user);
    }

    // ── ReconciliationRule ──

    public function test_reconciliation_rule_belongs_to_tenant(): void
    {
        $rule = ReconciliationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Regra padrão',
            'match_field' => 'amount',
            'match_operator' => 'equals',
            'priority' => 1,
        ]);

        $this->assertEquals($this->tenant->id, $rule->tenant_id);
    }

    // ── GeofenceLocation ──

    public function test_geofence_location_belongs_to_tenant(): void
    {
        $geo = GeofenceLocation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Escritório Central',
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'radius_meters' => 100,
        ]);

        $this->assertEquals($this->tenant->id, $geo->tenant_id);
    }

    // ── VisitCheckin ──

    public function test_visit_checkin_belongs_to_user(): void
    {
        $checkin = VisitCheckin::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'checkin_at' => now(),
            'checkin_lat' => -23.5505,
            'checkin_lng' => -46.6333,
            'status' => 'checked_in',
        ]);

        $this->assertInstanceOf(User::class, $checkin->user);
    }

    public function test_visit_checkin_belongs_to_customer(): void
    {
        $checkin = VisitCheckin::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'checkin_at' => now(),
            'checkin_lat' => -23.5505,
            'checkin_lng' => -46.6333,
            'status' => 'checked_in',
        ]);

        $this->assertInstanceOf(Customer::class, $checkin->customer);
    }
}
