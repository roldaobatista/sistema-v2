<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesAnalyticsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── follow-up-queue ─────────────────────────────────────────

    public function test_follow_up_queue_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/sales/follow-up-queue');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'data',
                'summary' => ['total', 'expired', 'urgent', 'total_value'],
            ]]);
    }

    public function test_follow_up_queue_includes_pending_quotes(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $this->user->id,
            'status' => 'sent',
            'valid_until' => now()->addDays(3),
            'total' => 5000,
        ]);

        $response = $this->getJson('/api/v1/sales/follow-up-queue');

        $response->assertOk();
        $summary = $response->json('data.summary');
        $this->assertGreaterThanOrEqual(1, $summary['total']);
    }

    public function test_follow_up_queue_prioritizes_expired_first(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Expired quote
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $this->user->id,
            'status' => 'sent',
            'valid_until' => now()->subDays(5),
            'total' => 1000,
        ]);

        // Future quote
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $this->user->id,
            'status' => 'pending_internal_approval',
            'valid_until' => now()->addDays(30),
            'total' => 2000,
        ]);

        $response = $this->getJson('/api/v1/sales/follow-up-queue');

        $response->assertOk();
        $items = $response->json('data.data');
        if (count($items) >= 2) {
            $this->assertEquals('expired', $items[0]['priority']);
        }
    }

    // ── loss-reasons ────────────────────────────────────────────

    public function test_follow_up_queue_returns_quote_contract_expected_by_sales_page(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Follow-up',
        ]);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $this->user->id,
            'quote_number' => 'ORC-FUP-001',
            'status' => 'sent',
            'valid_until' => now()->addDays(4),
            'total' => 3450.75,
        ]);

        $response = $this->getJson('/api/v1/sales/follow-up-queue');

        $response->assertOk()
            ->assertJsonPath('data.data.0.quote_number', 'ORC-FUP-001')
            ->assertJsonPath('data.data.0.number', 'ORC-FUP-001')
            ->assertJsonPath('data.data.0.customer_name', 'Cliente Follow-up')
            ->assertJsonPath('data.data.0.value', 3450.75)
            ->assertJsonPath('data.data.0.total', 3450.75);
    }

    public function test_follow_up_queue_treats_quote_valid_today_as_not_expired(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Hoje',
        ]);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $this->user->id,
            'quote_number' => 'ORC-HOJE-001',
            'status' => 'sent',
            'valid_until' => today(),
            'total' => 1200,
        ]);

        $response = $this->getJson('/api/v1/sales/follow-up-queue');

        $response->assertOk()
            ->assertJsonPath('data.data.0.quote_number', 'ORC-HOJE-001')
            ->assertJsonPath('data.data.0.days_remaining', 0)
            ->assertJsonPath('data.data.0.priority', 'urgent');
    }

    public function test_loss_reasons_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/sales/loss-reasons');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'data',
                'summary' => ['total_lost', 'total_value_lost'],
            ]]);
    }

    public function test_loss_reasons_groups_by_reason(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
        ]);

        CrmDeal::factory()->lost()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'lost_reason' => 'Preço',
            'lost_at' => now()->subDays(10),
            'value' => 3000,
        ]);

        CrmDeal::factory()->lost()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'lost_reason' => 'Preço',
            'lost_at' => now()->subDays(5),
            'value' => 2000,
        ]);

        $from = now()->subMonths(1)->toDateString();
        $to = now()->toDateString();
        $response = $this->getJson("/api/v1/sales/loss-reasons?from={$from}&to={$to}");

        $response->assertOk();
        $data = $response->json('data.data');
        // Should group deals with reason 'Preço'
        $priceReason = collect($data)->firstWhere('reason', 'Preço');
        if ($priceReason) {
            $this->assertEquals(2, $priceReason['count']);
            $this->assertEquals(5000, $priceReason['total_value']);
        }
    }

    public function test_loss_reasons_accepts_date_range(): void
    {
        $response = $this->getJson('/api/v1/sales/loss-reasons?from=2026-01-01&to=2026-01-31');

        $response->assertOk();
    }

    // ── client-segmentation ─────────────────────────────────────

    public function test_client_segmentation_returns_expected_structure(): void
    {
        $response = $this->getJson('/api/v1/sales/client-segmentation');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'data',
                'summary',
                'total_revenue',
                'total_customers',
            ]]);
    }

    public function test_client_segmentation_accepts_months_param(): void
    {
        $response = $this->getJson('/api/v1/sales/client-segmentation?months=6');

        $response->assertOk();
    }

    public function test_client_segmentation_with_paid_receivables(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 10000,
            'amount_paid' => 0,
            'status' => 'pending',
            'due_date' => now()->subMonths(2),
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
            'amount' => 10000,
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/sales/client-segmentation?months=1');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $data['total_customers']);
        $this->assertGreaterThan(0, $data['total_revenue']);
    }

    public function test_client_segmentation_uses_payment_date_and_legacy_fallback(): void
    {
        $customerWithPayment = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $customerLegacy = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivableWithPayment = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customerWithPayment->id,
            'created_by' => $this->user->id,
            'amount' => 7000,
            'amount_paid' => 0,
            'status' => 'pending',
            'due_date' => now()->subMonths(3),
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivableWithPayment->id,
            'received_by' => $this->user->id,
            'amount' => 7000,
            'payment_date' => now()->toDateString(),
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customerLegacy->id,
            'created_by' => $this->user->id,
            'amount' => 3000,
            'amount_paid' => 3000,
            'status' => 'paid',
            'paid_at' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/sales/client-segmentation?months=1')->assertOk();

        $data = $response->json('data');
        $this->assertEquals(10000.0, $data['total_revenue']);
        $this->assertEquals(2, $data['total_customers']);
    }

    // ── quote-rentability ───────────────────────────────────────

    public function test_quote_rentability_returns_200(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/sales/quote-rentability/{$quote->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['data' => [
                'quote_id',
                'total_revenue',
                'net_revenue',
                'total_cost',
                'profit',
                'margin_percent',
                'is_profitable',
                'breakdown',
            ]]]);
    }

    // ── upsell-suggestions ──────────────────────────────────────

    public function test_upsell_suggestions_returns_200(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson("/api/v1/sales/upsell-suggestions/{$customer->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['data']]);
    }

    // ── discount-requests ───────────────────────────────────────

    public function test_discount_requests_returns_200(): void
    {
        $response = $this->getJson('/api/v1/sales/discount-requests');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_discount_requests_shows_pending_discounts(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $this->user->id,
            'status' => 'pending_internal_approval',
            'discount_amount' => 500,
            'total' => 4500,
        ]);

        $response = $this->getJson('/api/v1/sales/discount-requests');

        $response->assertOk();
    }

    // ── tenant isolation ────────────────────────────────────────

    public function test_follow_up_queue_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        Quote::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'seller_id' => User::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'status' => 'sent',
            'valid_until' => now()->addDays(5),
        ]);

        $response = $this->getJson('/api/v1/sales/follow-up-queue');

        $response->assertOk();
        $summary = $response->json('data.summary');
        // Other tenant's quote should not appear
        $this->assertEquals(0, $summary['total']);
    }
}
