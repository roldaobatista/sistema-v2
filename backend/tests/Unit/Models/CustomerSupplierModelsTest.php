<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CustomerSupplierModelsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
        $this->actingAs($this->user);
    }

    // ── Customer — Relationships ──

    public function test_customer_has_many_work_orders(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        WorkOrder::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        $this->assertCount(2, $customer->workOrders);
    }

    public function test_customer_has_many_equipments(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Equipment::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        $this->assertCount(3, $customer->equipments);
    }

    public function test_customer_has_many_quotes(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Quote::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

        $this->assertCount(2, $customer->quotes);
    }

    public function test_customer_belongs_to_tenant(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $customer->tenant_id);
    }

    public function test_customer_soft_delete(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $id = $customer->id;
        $customer->delete();

        $this->assertNull(Customer::find($id));
        $this->assertNotNull(Customer::withTrashed()->find($id));
    }

    public function test_customer_fillable_contains_required_fields(): void
    {
        $customer = new Customer;
        $fillable = $customer->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('phone', $fillable);
    }

    public function test_customer_with_document(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document' => '12345678901',
        ]);

        $this->assertEquals('12345678901', $customer->document);
    }

    // ── Supplier — Relationships ──

    public function test_supplier_belongs_to_tenant(): void
    {
        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $supplier->tenant_id);
    }

    public function test_supplier_has_fillable_fields(): void
    {
        $supplier = new Supplier;
        $fillable = $supplier->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('tenant_id', $fillable);
    }

    public function test_supplier_soft_delete(): void
    {
        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $id = $supplier->id;
        $supplier->delete();

        $this->assertNull(Supplier::find($id));
        $this->assertNotNull(Supplier::withTrashed()->find($id));
    }
}
