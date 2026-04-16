<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Events\QuoteApproved;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteStatusFlowTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Equipment $equipment;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
        Gate::before(fn () => true);
    }

    private function makeQuoteWithItems(array $attrs = []): Quote
    {
        $quote = Quote::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::DRAFT->value,
        ], $attrs));

        $eq = $quote->equipments()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'description' => 'Manutenção',
            'sort_order' => 0,
        ]);

        $eq->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 2,
            'original_price' => 500,
            'unit_price' => 500,
            'discount_percentage' => 0,
            'sort_order' => 0,
        ]);

        return $quote;
    }

    // ── 2.1 Transição para INVOICED ───────────────────────────────────

    public function test_mark_as_invoiced_from_approved(): void
    {
        $quote = $this->makeQuoteWithItems(['status' => QuoteStatus::APPROVED->value]);

        $this->postJson("/api/v1/quotes/{$quote->id}/invoice")
            ->assertOk()
            ->assertJsonPath('data.status', 'invoiced');

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => 'invoiced',
        ]);
    }

    public function test_mark_as_invoiced_from_in_execution(): void
    {
        $quote = $this->makeQuoteWithItems(['status' => QuoteStatus::IN_EXECUTION->value]);

        $this->postJson("/api/v1/quotes/{$quote->id}/invoice")
            ->assertOk()
            ->assertJsonPath('data.status', 'invoiced');
    }

    public function test_mark_as_invoiced_blocked_from_draft(): void
    {
        $quote = $this->makeQuoteWithItems(['status' => QuoteStatus::DRAFT->value]);

        $this->postJson("/api/v1/quotes/{$quote->id}/invoice")
            ->assertUnprocessable();
    }

    public function test_mark_as_invoiced_blocked_from_sent(): void
    {
        $quote = $this->makeQuoteWithItems(['status' => QuoteStatus::SENT->value]);

        $this->postJson("/api/v1/quotes/{$quote->id}/invoice")
            ->assertUnprocessable();
    }

    // ── 2.2 approveAfterTest dispara QuoteApproved ────────────────────

    public function test_approve_after_test_dispatches_event(): void
    {
        Event::fake([QuoteApproved::class]);

        $quote = $this->makeQuoteWithItems([
            'status' => QuoteStatus::INSTALLATION_TESTING->value,
            'seller_id' => $this->user->id,
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/approve-after-test", ['approval_channel' => 'whatsapp', 'terms_accepted' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        Event::assertDispatched(QuoteApproved::class);
    }

    public function test_approve_after_test_blocked_from_draft(): void
    {
        $quote = $this->makeQuoteWithItems(['status' => QuoteStatus::DRAFT->value]);

        $this->postJson("/api/v1/quotes/{$quote->id}/approve-after-test", ['approval_channel' => 'whatsapp', 'terms_accepted' => true])
            ->assertUnprocessable();
    }

    // ── 2.3 internalApprove de DRAFT (aprovação direta por gerente) ────

    public function test_internal_approve_from_draft_succeeds(): void
    {
        $quote = $this->makeQuoteWithItems(['status' => QuoteStatus::DRAFT->value]);

        $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'internally_approved');
    }

    public function test_internal_approve_blocked_without_items(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => QuoteStatus::DRAFT->value,
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve")
            ->assertUnprocessable();
    }

    public function test_internal_approve_from_pending_succeeds(): void
    {
        $quote = $this->makeQuoteWithItems(['status' => QuoteStatus::PENDING_INTERNAL_APPROVAL->value]);

        $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'internally_approved');
    }

    // ── 2.4 QuoteService::updateQuote protegido com isMutable ─────────

    public function test_service_update_blocked_for_sent_quote(): void
    {
        $quote = $this->makeQuoteWithItems(['status' => QuoteStatus::SENT->value]);
        $service = app(QuoteService::class);

        $this->expectException(\DomainException::class);
        $service->updateQuote($quote, ['observations' => 'Tentativa']);
    }

    public function test_service_update_allowed_for_draft(): void
    {
        $quote = $this->makeQuoteWithItems(['status' => QuoteStatus::DRAFT->value]);
        $service = app(QuoteService::class);

        $updated = $service->updateQuote($quote, ['observations' => 'Atualizado']);
        $this->assertEquals('Atualizado', $updated->observations);
    }

    // ── 2.5 reopenQuote limpa valid_until expirado ────────────────────

    public function test_reopen_clears_expired_valid_until(): void
    {
        $quote = $this->makeQuoteWithItems([
            'status' => QuoteStatus::EXPIRED->value,
            'valid_until' => now()->subDays(10),
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/reopen")
            ->assertOk();

        $quote->refresh();
        $this->assertNull($quote->valid_until);
        $this->assertEquals('draft', $quote->status->value);
    }

    public function test_reopen_keeps_future_valid_until(): void
    {
        $futureDate = now()->addDays(30)->format('Y-m-d');
        $quote = $this->makeQuoteWithItems([
            'status' => QuoteStatus::REJECTED->value,
            'valid_until' => $futureDate,
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/reopen")
            ->assertOk();

        $quote->refresh();
        $this->assertNotNull($quote->valid_until);
        $this->assertEquals($futureDate, $quote->valid_until->format('Y-m-d'));
    }

    // ── 2.6 Fluxo completo: draft → pending → approved_internal → sent → approved → invoiced

    public function test_full_happy_path_flow(): void
    {
        Event::fake([QuoteApproved::class]);

        $quote = $this->makeQuoteWithItems();

        // Step 1: request internal approval
        $this->postJson("/api/v1/quotes/{$quote->id}/request-internal-approval")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_internal_approval');

        // Step 2: internal approve
        $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'internally_approved');

        // Step 3: send to client
        $this->postJson("/api/v1/quotes/{$quote->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        // Step 4: approve
        $this->postJson("/api/v1/quotes/{$quote->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        Event::assertDispatched(QuoteApproved::class);

        // Step 5: invoice
        $this->postJson("/api/v1/quotes/{$quote->id}/invoice")
            ->assertOk()
            ->assertJsonPath('data.status', 'invoiced');
    }

    // ── 2.7 Fluxo: sent → rejected → reopen → resend → approved

    public function test_rejection_and_reopen_flow(): void
    {
        Event::fake([QuoteApproved::class]);

        $quote = $this->makeQuoteWithItems(['status' => QuoteStatus::SENT->value, 'magic_token' => 'tok']);

        // Reject
        $this->postJson("/api/v1/quotes/{$quote->id}/reject", ['reason' => 'Muito caro'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        // Reopen
        $this->postJson("/api/v1/quotes/{$quote->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        // Request internal approval again
        $this->postJson("/api/v1/quotes/{$quote->id}/request-internal-approval")
            ->assertOk();

        // Internal approve
        $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve")
            ->assertOk();

        // Send again
        $this->postJson("/api/v1/quotes/{$quote->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        // Approve
        $this->postJson("/api/v1/quotes/{$quote->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        Event::assertDispatched(QuoteApproved::class);
    }
}
