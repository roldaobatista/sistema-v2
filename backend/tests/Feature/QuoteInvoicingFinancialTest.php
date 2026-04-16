<?php

namespace Tests\Feature;

use App\Enums\FinancialStatus;
use App\Enums\QuoteStatus;
use App\Http\Middleware\CheckPermission;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Tests for financial integration when invoicing quotes.
 * Verifies that AccountReceivable is created automatically.
 */
class QuoteInvoicingFinancialTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private Customer $customer;

    private QuoteService $service;

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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->service = app(QuoteService::class);
    }

    private function createApprovedQuote(string $total = '1500.00', ?string $paymentTerms = '30_dias'): Quote
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->admin->id,
            'status' => QuoteStatus::APPROVED,
            'total' => $total,
            'payment_terms' => $paymentTerms,
            'quote_number' => 'ORC-'.str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT),
        ]);

        // Create equipment with item for realistic data
        $eq = QuoteEquipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'quote_id' => $quote->id,
        ]);

        QuoteItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'quote_equipment_id' => $eq->id,
            'unit_price' => $total,
            'quantity' => 1,
        ]);

        return $quote;
    }

    public function test_invoicing_creates_account_receivable(): void
    {
        $quote = $this->createApprovedQuote('2500.00');

        $this->service->markAsInvoiced($quote, $this->admin->id);

        $this->assertDatabaseHas('accounts_receivable', [
            'quote_id' => $quote->id,
            'customer_id' => $this->customer->id,
            'origin_type' => 'quote',
            'amount' => '2500.00',
            'amount_paid' => '0.00',
            'status' => FinancialStatus::PENDING->value,
        ]);
    }

    public function test_invoicing_sets_correct_due_date_from_payment_terms(): void
    {
        $quote = $this->createApprovedQuote('1000.00', '30_dias');

        $this->service->markAsInvoiced($quote, $this->admin->id);

        $ar = AccountReceivable::where('quote_id', $quote->id)->firstOrFail();
        $expectedDate = now()->addDays(30)->format('Y-m-d');
        $this->assertEquals($expectedDate, $ar->due_date->format('Y-m-d'));
    }

    public function test_invoicing_with_a_vista_sets_today_as_due_date(): void
    {
        $quote = $this->createApprovedQuote('800.00', 'a_vista');

        $this->service->markAsInvoiced($quote, $this->admin->id);

        $ar = AccountReceivable::where('quote_id', $quote->id)->firstOrFail();
        $this->assertEquals(now()->format('Y-m-d'), $ar->due_date->format('Y-m-d'));
    }

    public function test_invoicing_with_null_payment_terms_defaults_to_30_days(): void
    {
        $quote = $this->createApprovedQuote('500.00', null);

        $this->service->markAsInvoiced($quote, $this->admin->id);

        $ar = AccountReceivable::where('quote_id', $quote->id)->firstOrFail();
        $expectedDate = now()->addDays(30)->format('Y-m-d');
        $this->assertEquals($expectedDate, $ar->due_date->format('Y-m-d'));
    }

    public function test_account_receivable_links_back_to_quote(): void
    {
        $quote = $this->createApprovedQuote();

        $this->service->markAsInvoiced($quote, $this->admin->id);

        $ar = AccountReceivable::where('quote_id', $quote->id)->firstOrFail();
        $this->assertNotNull($ar->quote);
        $this->assertEquals($quote->id, $ar->quote->id);
    }

    public function test_quote_has_account_receivables_relationship(): void
    {
        $quote = $this->createApprovedQuote();

        $this->service->markAsInvoiced($quote, $this->admin->id);

        $quote->refresh();
        $this->assertCount(1, $quote->accountReceivables);
        $this->assertEquals('quote', $quote->accountReceivables->first()->origin_type);
    }

    public function test_invoicing_changes_quote_status_to_invoiced(): void
    {
        $quote = $this->createApprovedQuote();

        $result = $this->service->markAsInvoiced($quote, $this->admin->id);

        $this->assertEquals(QuoteStatus::INVOICED, $result->status);
    }

    public function test_invoicing_in_execution_quote_also_creates_receivable(): void
    {
        $quote = $this->createApprovedQuote('3000.00');
        $quote->update(['status' => QuoteStatus::IN_EXECUTION->value]);

        $this->service->markAsInvoiced($quote, $this->admin->id);

        $this->assertDatabaseHas('accounts_receivable', [
            'quote_id' => $quote->id,
            'amount' => '3000.00',
            'origin_type' => 'quote',
        ]);
    }

    public function test_invoicing_draft_quote_throws_exception(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::DRAFT,
        ]);

        $this->expectException(\DomainException::class);
        $this->service->markAsInvoiced($quote, $this->admin->id);
    }

    public function test_show_endpoint_includes_account_receivables(): void
    {
        $quote = $this->createApprovedQuote();
        $this->service->markAsInvoiced($quote, $this->admin->id);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/quotes/{$quote->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'account_receivables' => [
                    ['id', 'quote_id', 'amount', 'due_date', 'status'],
                ],
            ],
        ]);
    }
}
