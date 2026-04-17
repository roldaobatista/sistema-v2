<?php

namespace Tests\Unit;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Testes de edge cases e limites:
 * valores zero, max precision, datas limítrofes,
 * campos opcionais nulos, cálculos de borda.
 */
class EdgeCasesAndBoundaryTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);
    }

    // ═══ Zero value edge cases ═══

    public function test_invoice_zero_total(): void
    {
        $inv = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '0.00',
        ]);
        $this->assertEquals('0.00', $inv->total);
    }

    public function test_invoice_zero_discount(): void
    {
        $inv = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'discount' => '0.00',
        ]);
        $this->assertEquals('0.00', $inv->discount);
    }

    public function test_ar_zero_amount(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '0.00',
            'amount_paid' => '0.00',
        ]);
        $this->assertEquals('0.00', $ar->amount);
    }

    public function test_ap_zero_amount(): void
    {
        $ap = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '0.00',
            'amount_paid' => '0.00',
        ]);
        $this->assertEquals('0.00', $ap->amount);
    }

    // ═══ High precision edge cases ═══

    public function test_expense_large_amount(): void
    {
        $ex = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '999999.99',
        ]);
        $this->assertEquals('999999.99', $ex->amount);
    }

    public function test_ar_large_amount(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => '500000.00',
        ]);
        $this->assertEquals('500000.00', $ar->amount);
    }

    // ═══ Null/optional fields ═══

    public function test_customer_without_email(): void
    {
        $c = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => null,
        ]);
        $this->assertNull($c->email);
    }

    public function test_customer_without_phone(): void
    {
        $c = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => null,
        ]);
        $this->assertNull($c->phone);
    }

    public function test_equipment_without_calibration_fields(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'precision_class' => null,
            'capacity' => null,
        ]);
        $this->assertNull($eq->precision_class);
    }

    public function test_wo_without_technician(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => null,
        ]);
        $this->assertNull($wo->assigned_to);
    }

    // ═══ Date boundary cases ═══

    public function test_ar_due_today(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'due_date' => now()->format('Y-m-d'),
        ]);
        $this->assertTrue($ar->due_date->isToday());
    }

    public function test_ar_due_yesterday_is_overdue(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'due_date' => now()->subDay()->format('Y-m-d'),
        ]);
        $this->assertTrue($ar->due_date->isPast());
    }

    public function test_ar_due_far_future(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'due_date' => now()->addYears(5)->format('Y-m-d'),
        ]);
        $this->assertTrue($ar->due_date->isFuture());
    }

    // ═══ WO Item edge cases ═══

    public function test_wo_item_zero_quantity(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $item = WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'quantity' => 0,
            'unit_price' => '500.00',
        ]);
        $total = bcmul($item->quantity, $item->unit_price, 2);
        $this->assertEquals('0.00', $total);
    }

    public function test_wo_item_fractional_quantity(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $item = WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'quantity' => 1.5,
            'unit_price' => '200.00',
        ]);
        $total = bcmul((string) $item->quantity, $item->unit_price, 2);
        $this->assertEquals('300.00', $total);
    }

    // ═══ Bulk edge cases ═══

    public function test_multiple_customers_same_document_across_tenants(): void
    {
        // Wave 5 (DATA-007): UNIQUE composto (tenant_id, document_hash, sentinela)
        // bloqueia mesmo CPF em mesmo tenant (regra de negócio: 1 doc/tenant).
        // Mesmo CPF em TENANTS DIFERENTES é permitido — Pessoa Física pode ser
        // cliente de duas empresas Kalibrium independentes simultaneamente.
        $otherTenant = Tenant::factory()->create();

        $c1 = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document' => '12345678901',
        ]);
        $c2 = Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'document' => '12345678901',
        ]);
        $this->assertNotEquals($c1->id, $c2->id);
        $this->assertNotEquals($c1->tenant_id, $c2->tenant_id);
    }

    public function test_wo_with_many_items(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        WorkOrderItem::factory()->count(20)->create(['work_order_id' => $wo->id, 'tenant_id' => $this->tenant->id]);
        $this->assertEquals(20, $wo->items()->count());
    }

    // ═══ Soft delete consistency ═══

    public function test_soft_deleted_customer_preserves_data(): void
    {
        $c = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Deletado',
        ]);
        $c->delete();
        $restored = Customer::withTrashed()->find($c->id);
        $this->assertEquals('Deletado', $restored->name);
    }

    public function test_soft_deleted_wo_preserves_customer_relation(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $wo->delete();
        $restored = WorkOrder::withTrashed()->find($wo->id);
        $this->assertEquals($this->customer->id, $restored->customer_id);
    }

    public function test_wo_deleted_items_not_included(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $item = WorkOrderItem::factory()->create(['work_order_id' => $wo->id]);
        $item->delete();
        $this->assertEquals(0, $wo->items()->count());
    }

    // ═══ Quote edge cases ═══

    public function test_quote_create(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertDatabaseHas('quotes', ['id' => $q->id]);
    }

    public function test_quote_soft_deletes(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $q->delete();
        $this->assertSoftDeleted($q);
    }
}
