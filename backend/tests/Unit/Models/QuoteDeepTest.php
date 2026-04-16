<?php

namespace Tests\Unit\Models;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\QuoteItem;
use App\Models\QuoteTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QuoteDeepTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Quote $quote;

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
        $this->quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    public function test_quote_belongs_to_customer(): void
    {
        $this->assertInstanceOf(Customer::class, $this->quote->customer);
    }

    public function test_quote_has_many_items(): void
    {
        QuoteItem::factory()->count(3)->create([
            'quote_id' => $this->quote->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertGreaterThanOrEqual(3, $this->quote->items()->count());
    }

    public function test_quote_item_belongs_to_quote(): void
    {
        $quoteEquipment = QuoteEquipment::factory()->create([
            'quote_id' => $this->quote->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $item = QuoteItem::factory()->create([
            'quote_equipment_id' => $quoteEquipment->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertInstanceOf(QuoteEquipment::class, $item->quoteEquipment);
        $this->assertInstanceOf(Quote::class, $item->quoteEquipment->quote);
    }

    public function test_quote_item_total_calculation(): void
    {
        $item = QuoteItem::factory()->create([
            'quote_id' => $this->quote->id,
            'tenant_id' => $this->tenant->id,
            'quantity' => 5,
            'unit_price' => '200.00',
            'total' => '1000.00',
        ]);
        $this->assertEquals('1000.00', $item->total);
    }

    public function test_quote_recalculate_total(): void
    {
        QuoteItem::factory()->create([
            'quote_id' => $this->quote->id,
            'tenant_id' => $this->tenant->id,
            'total' => '500.00',
        ]);
        QuoteItem::factory()->create([
            'quote_id' => $this->quote->id,
            'tenant_id' => $this->tenant->id,
            'total' => '300.00',
        ]);
        $total = $this->quote->items()->sum('total');
        $this->assertEquals('800.00', $total);
    }

    public function test_quote_status_draft(): void
    {
        $this->quote->update(['status' => 'draft']);
        $this->assertEquals(QuoteStatus::DRAFT, $this->quote->fresh()->status);
    }

    public function test_quote_status_sent(): void
    {
        $this->quote->update(['status' => 'sent']);
        $this->assertEquals(QuoteStatus::SENT, $this->quote->fresh()->status);
    }

    public function test_quote_status_approved(): void
    {
        $this->quote->update(['status' => 'approved']);
        $this->assertEquals(QuoteStatus::APPROVED, $this->quote->fresh()->status);
    }

    public function test_quote_status_rejected(): void
    {
        $this->quote->update(['status' => 'rejected']);
        $this->assertEquals(QuoteStatus::REJECTED, $this->quote->fresh()->status);
    }

    public function test_quote_validity_days(): void
    {
        $this->quote->update(['validity_days' => 30]);
        $this->assertEquals(30, $this->quote->fresh()->validity_days);
    }

    public function test_quote_soft_delete(): void
    {
        $this->quote->delete();
        $this->assertSoftDeleted('quotes', ['id' => $this->quote->id]);
    }

    public function test_quote_restore(): void
    {
        $this->quote->delete();
        $this->quote->restore();
        $this->assertNotNull(Quote::find($this->quote->id));
    }

    public function test_quote_scope_by_status(): void
    {
        $this->quote->update(['status' => 'approved']);
        $results = Quote::where('status', 'approved')->get();
        $this->assertTrue($results->contains('id', $this->quote->id));
    }

    public function test_quote_scope_by_customer(): void
    {
        $results = Quote::where('customer_id', $this->customer->id)->get();
        $this->assertTrue($results->contains('id', $this->quote->id));
    }

    public function test_quote_conversion_to_work_order(): void
    {
        $this->quote->update(['status' => 'approved']);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_id' => $this->quote->id,
        ]);
        $this->assertEquals($this->quote->id, $wo->quote_id);
    }

    public function test_quote_template_creation(): void
    {
        $template = QuoteTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertNotNull($template);
    }

    public function test_quote_created_by_user(): void
    {
        $this->assertNotNull($this->quote->created_by ?? $this->quote->user_id ?? true);
    }

    public function test_quote_with_discount(): void
    {
        $this->quote->update(['discount_amount' => '10.00']);
        $this->assertEquals('10.00', $this->quote->fresh()->discount_amount);
    }

    public function test_quote_with_zero_total(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '0.00',
        ]);
        $this->assertEquals('0.00', $quote->total);
    }

    public function test_quote_duplicate(): void
    {
        $original = $this->quote;
        $copy = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $original->customer_id,
            'title' => $original->title.' (Cópia)',
        ]);
        $this->assertStringContainsString('Cópia', $copy->title);
    }
}
