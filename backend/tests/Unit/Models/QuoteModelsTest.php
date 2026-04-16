<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QuoteModelsTest extends TestCase
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

    // ── Quote — Relationships ──

    public function test_quote_belongs_to_customer(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $this->assertInstanceOf(Customer::class, $quote->customer);
    }

    public function test_quote_has_created_by_field(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals($this->user->id, $quote->created_by);
    }

    public function test_quote_has_many_equipments(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $this->assertInstanceOf(HasMany::class, $quote->equipments());
    }

    public function test_quote_has_many_work_orders(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_id' => $quote->id,
        ]);

        $this->assertGreaterThanOrEqual(1, $quote->workOrders()->count());
    }

    public function test_quote_belongs_to_seller(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $quote->seller);
    }

    // ── Quote — Scopes & Business Logic ──

    public function test_quote_soft_delete(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $quote->delete();

        $this->assertNull(Quote::find($quote->id));
        $this->assertNotNull(Quote::withTrashed()->find($quote->id));
    }

    public function test_quote_decimal_casts(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '15000.50',
            'discount_amount' => '500.00',
        ]);

        $quote->refresh();
        $this->assertEquals('15000.50', $quote->total);
    }

    public function test_quote_fillable_contains_essential_fields(): void
    {
        $quote = new Quote;
        $fillable = $quote->getFillable();

        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('customer_id', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('total', $fillable);
    }

    // ── QuoteItem — Relationships ──

    public function test_quote_item_belongs_to_quote_equipment(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $quoteEquipment = QuoteEquipment::create([
            'tenant_id' => $this->tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Equipamento teste',
        ]);
        $item = QuoteItem::create([
            'quote_equipment_id' => $quoteEquipment->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'service',
            'custom_description' => 'Calibração Balança',
            'quantity' => 1,
            'original_price' => '500.00',
            'unit_price' => '500.00',
        ]);

        $this->assertInstanceOf(QuoteEquipment::class, $item->quoteEquipment);
    }

    public function test_quote_item_decimal_casts(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $quoteEquipment = QuoteEquipment::create([
            'tenant_id' => $this->tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Equipamento teste',
        ]);
        $item = QuoteItem::create([
            'quote_equipment_id' => $quoteEquipment->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'service',
            'custom_description' => 'Serviço teste',
            'quantity' => 3,
            'original_price' => '250.75',
            'unit_price' => '250.75',
        ]);

        $item->refresh();
        $this->assertEquals('250.75', $item->unit_price);
        // Event::fake() prevents saving hook from auto-calculating subtotal.
        // Verify the decimal cast works on the unit_price field.
        $this->assertIsString($item->unit_price);
    }
}
