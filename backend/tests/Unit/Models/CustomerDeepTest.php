<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CustomerDeepTest extends TestCase
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

    // ── Relationships ──

    public function test_customer_has_many_work_orders(): void
    {
        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertGreaterThanOrEqual(3, $this->customer->workOrders()->count());
    }

    public function test_customer_has_many_equipments(): void
    {
        Equipment::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertGreaterThanOrEqual(2, $this->customer->equipments()->count());
    }

    public function test_customer_has_many_quotes(): void
    {
        Quote::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertGreaterThanOrEqual(2, $this->customer->quotes()->count());
    }

    public function test_customer_has_contacts_relationship(): void
    {
        $this->assertInstanceOf(
            HasMany::class,
            $this->customer->contacts()
        );
    }

    // ── Scopes ──

    public function test_scope_search_by_name(): void
    {
        $c = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Empresa Kalibrium Teste XYZ',
        ]);
        $results = Customer::where('name', 'like', '%Kalibrium Teste XYZ%')->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_scope_search_by_document(): void
    {
        $c = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document' => '12345678000199',
        ]);
        // `document` é encrypted (cast `encrypted`) — busca por igualdade direta
        // não funciona (ciphertext difere a cada gravação por causa do IV).
        // Wave 1B: usar `document_hash` (HMAC-SHA256 determinístico).
        $results = Customer::where('document_hash', Customer::hashSearchable('12345678000199', digitsOnly: true))->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertSame('12345678000199', $results->first()->document);
    }

    public function test_scope_active_customers(): void
    {
        $active = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $inactive = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);
        $results = Customer::where('is_active', true)->get();
        $this->assertTrue($results->contains('id', $active->id));
    }

    public function test_scope_by_type_company(): void
    {
        $company = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'PJ',
        ]);
        $results = Customer::where('type', 'PJ')->get();
        $this->assertTrue($results->contains('id', $company->id));
    }

    public function test_scope_by_type_individual(): void
    {
        $individual = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'PF',
        ]);
        $results = Customer::where('type', 'PF')->get();
        $this->assertTrue($results->contains('id', $individual->id));
    }

    // ── Business Logic ──

    public function test_customer_open_work_orders_count(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
        $openCount = $this->customer->workOrders()->where('status', WorkOrder::STATUS_OPEN)->count();
        $this->assertEquals(1, $openCount);
    }

    public function test_customer_total_revenue(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '5000.00',
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '3000.00',
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
        $totalRevenue = $this->customer->workOrders()
            ->where('status', WorkOrder::STATUS_COMPLETED)
            ->sum('total');
        $this->assertEquals('8000.00', $totalRevenue);
    }

    public function test_customer_equipment_count(): void
    {
        Equipment::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertEquals(5, $this->customer->equipments()->count());
    }

    // ── Soft Deletes ──

    public function test_deleting_customer_preserves_work_orders(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->customer->delete();
        $this->assertNotNull(WorkOrder::find($wo->id));
    }

    public function test_restoring_customer(): void
    {
        $this->customer->delete();
        $this->customer->restore();
        $this->assertNotNull(Customer::find($this->customer->id));
    }

    // ── Fillable / Guarded ──

    public function test_customer_fillable_fields(): void
    {
        $c = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Teste',
            'email' => 'teste@t.com',
            'phone' => '11999887766',
            'document' => '12345678901',
            'type' => 'PJ',
        ]);
        $this->assertEquals('Teste', $c->name);
        $this->assertEquals('teste@t.com', $c->email);
    }

    // ── Casts ──

    public function test_customer_is_active_boolean_cast(): void
    {
        $c = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $c->refresh();
        $this->assertIsBool($c->is_active);
    }

    public function test_customer_created_at_is_carbon(): void
    {
        $this->assertInstanceOf(Carbon::class, $this->customer->created_at);
    }

    // ── Edge Cases ──

    public function test_customer_with_special_characters(): void
    {
        $c = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => "Empresa D'Angelo & Filhos Ltda",
        ]);
        $this->assertEquals("Empresa D'Angelo & Filhos Ltda", $c->name);
    }

    public function test_customer_with_long_name(): void
    {
        $longName = str_repeat('A', 200);
        $c = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $longName,
        ]);
        $this->assertEquals($longName, $c->name);
    }
}
