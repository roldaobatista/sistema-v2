<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmDealProduct;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmDealConvertToQuoteTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private CrmPipeline $pipeline;

    private CrmPipelineStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        // Event::fake();

        Gate::before(fn () => true);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
            'probability' => 50,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_convert_deal_to_quote_without_products(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
            'title' => 'Contrato calibração',
            'value' => '15000.00',
            'assigned_to' => $this->user->id,
            'notes' => 'Notas do negócio',
            'source' => 'indicacao',
        ]);

        $response = $this->postJson("/api/v1/crm/deals/{$deal->id}/convert-to-quote");

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'quote' => ['id', 'quote_number', 'customer_id', 'seller_id', 'status'],
                'message',
            ],
        ]);

        $quote = Quote::find($response->json('data.quote.id'));
        $this->assertNotNull($quote);
        $this->assertEquals($this->customer->id, $quote->customer_id);
        $this->assertEquals($this->user->id, $quote->seller_id);
        $this->assertEquals($this->user->id, $quote->created_by);
        $this->assertEquals('draft', $quote->status->value);
        $this->assertEquals('indicacao', $quote->source);
        $this->assertStringContains('Notas do negócio', $quote->observations);
        $this->assertEquals('15000.00', $quote->total);

        // Deal should be linked to quote
        $deal->refresh();
        $this->assertEquals($quote->id, $deal->quote_id);
    }

    public function test_convert_deal_to_quote_with_products(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
            'title' => 'Deal com produtos',
            'value' => '25000.00',
            'assigned_to' => $this->user->id,
        ]);

        $product1 = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $product2 = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        CrmDealProduct::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'product_id' => $product1->id,
            'quantity' => 3,
            'unit_price' => '1000.00',
        ]);

        CrmDealProduct::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'product_id' => $product2->id,
            'quantity' => 2,
            'unit_price' => '500.00',
        ]);

        $response = $this->postJson("/api/v1/crm/deals/{$deal->id}/convert-to-quote");

        $response->assertStatus(201);

        $quoteId = $response->json('data.quote.id');
        $quote = Quote::find($quoteId);
        $this->assertNotNull($quote);

        // Should have created a QuoteEquipment group
        $equipments = QuoteEquipment::where('quote_id', $quoteId)->get();
        $this->assertCount(1, $equipments);
        $this->assertEquals('Itens do negócio', $equipments->first()->description);

        // Should have created 2 QuoteItems
        $items = QuoteItem::where('quote_id', $quoteId)->orderBy('sort_order')->get();
        $this->assertCount(2, $items);

        // Verify first item mapping
        $item1 = $items->firstWhere('product_id', $product1->id);
        $this->assertNotNull($item1);
        $this->assertEquals('3.00', $item1->quantity);
        $this->assertEquals('1000.00', $item1->unit_price);

        // Verify second item mapping
        $item2 = $items->firstWhere('product_id', $product2->id);
        $this->assertNotNull($item2);
        $this->assertEquals('2.00', $item2->quantity);
        $this->assertEquals('500.00', $item2->unit_price);

        // Total should be recalculated from items: (3*1000) + (2*500) = 4000
        $quote->refresh();
        $this->assertEquals('4000.00', $quote->total);

        // Deal should be linked
        $deal->refresh();
        $this->assertEquals($quoteId, $deal->quote_id);
    }

    public function test_convert_lost_deal_returns_422(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_LOST,
            'lost_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/crm/deals/{$deal->id}/convert-to-quote");

        $response->assertStatus(422);
        $this->assertNull($deal->fresh()->quote_id);
    }

    public function test_convert_deal_already_converted_returns_422(): void
    {
        $existingQuote = Quote::factory()->create(['tenant_id' => $this->tenant->id]);

        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
            'quote_id' => $existingQuote->id,
        ]);

        $response = $this->postJson("/api/v1/crm/deals/{$deal->id}/convert-to-quote");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Este negócio já possui um orçamento vinculado.']);
    }

    public function test_convert_won_deal_to_quote_succeeds(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_WON,
            'won_at' => now(),
            'title' => 'Deal ganho',
            'value' => '10000.00',
            'assigned_to' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/crm/deals/{$deal->id}/convert-to-quote");

        $response->assertStatus(201);
        $deal->refresh();
        $this->assertNotNull($deal->quote_id);
    }

    public function test_convert_deal_creates_crm_activity_log(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
            'title' => 'Deal para atividade',
            'value' => '5000.00',
        ]);

        $response = $this->postJson("/api/v1/crm/deals/{$deal->id}/convert-to-quote");
        $response->assertStatus(201);

        $activity = CrmActivity::where('deal_id', $deal->id)
            ->where('type', CrmActivity::TYPE_SYSTEM)
            ->first();

        $this->assertNotNull($activity);
        $this->assertStringContains('criado a partir do negócio', $activity->title);
    }

    public function test_cross_tenant_deal_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $deal = CrmDeal::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $response = $this->postJson("/api/v1/crm/deals/{$deal->id}/convert-to-quote");

        // Should return 404 due to tenant scope or explicit tenant check
        $this->assertTrue(in_array($response->status(), [404, 403]));
    }

    public function test_convert_deal_maps_source_correctly(): void
    {
        // Test with compatible source
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
            'title' => 'Deal prospecção',
            'value' => '8000.00',
            'source' => 'prospeccao',
        ]);

        $response = $this->postJson("/api/v1/crm/deals/{$deal->id}/convert-to-quote");
        $response->assertStatus(201);

        $quote = Quote::find($response->json('data.quote.id'));
        $this->assertEquals('prospeccao', $quote->source);
    }

    public function test_convert_deal_with_incompatible_source_sets_null(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
            'title' => 'Deal chamado',
            'value' => '3000.00',
            'source' => 'chamado',
        ]);

        $response = $this->postJson("/api/v1/crm/deals/{$deal->id}/convert-to-quote");
        $response->assertStatus(201);

        $quote = Quote::find($response->json('data.quote.id'));
        $this->assertNull($quote->source);
    }

    /**
     * Helper: assert string contains substring.
     */
    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
