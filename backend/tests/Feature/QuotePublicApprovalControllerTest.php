<?php

namespace Tests\Feature;

use App\Events\QuoteApproved;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QuotePublicApprovalControllerTest extends TestCase
{
    public function test_magic_link_show_returns_quote_items_and_pdf_url(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $seller = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
            'magic_token' => str_repeat('b', 64),
            'valid_until' => now()->addDays(7),
        ]);

        $equipment = $quote->equipments()->create([
            'tenant_id' => $tenant->id,
            'equipment_id' => null,
            'description' => 'Equipamento teste',
            'sort_order' => 0,
        ]);

        $equipment->items()->create([
            'tenant_id' => $tenant->id,
            'type' => 'service',
            'service_id' => null,
            'custom_description' => 'Servico de calibracao',
            'quantity' => 2,
            'original_price' => 125,
            'unit_price' => 125,
            'discount_percentage' => 0,
            'sort_order' => 0,
        ]);

        $this->getJson("/api/v1/quotes/proposal/{$quote->magic_token}")
            ->assertOk()
            ->assertJsonPath('data.quote_number', $quote->quote_number)
            ->assertJsonPath('data.items.0.description', 'Servico de calibracao')
            ->assertJsonPath('data.pdf_url', $quote->pdf_url);

        $quote->refresh();

        $this->assertSame(1, $quote->client_view_count);
        $this->assertNotNull($quote->client_viewed_at);
    }

    public function test_magic_link_show_does_not_lazy_load_items_when_strict_mode_is_enabled(): void
    {
        Model::preventLazyLoading(true);

        try {
            $tenant = Tenant::factory()->create();
            app()->instance('current_tenant_id', $tenant->id);

            $seller = User::factory()->create([
                'tenant_id' => $tenant->id,
                'current_tenant_id' => $tenant->id,
            ]);
            $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

            $quote = Quote::factory()->create([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'seller_id' => $seller->id,
                'status' => Quote::STATUS_SENT,
                'magic_token' => str_repeat('f', 64),
                'valid_until' => now()->addDays(5),
            ]);

            $equipment = $quote->equipments()->create([
                'tenant_id' => $tenant->id,
                'equipment_id' => null,
                'description' => 'Equipamento publico',
                'sort_order' => 0,
            ]);

            $equipment->items()->create([
                'tenant_id' => $tenant->id,
                'type' => 'service',
                'service_id' => null,
                'custom_description' => 'Servico sem lazy loading',
                'quantity' => 1,
                'original_price' => 250,
                'unit_price' => 250,
                'discount_percentage' => 0,
                'sort_order' => 0,
            ]);

            $this->getJson("/api/v1/quotes/proposal/{$quote->magic_token}")
                ->assertOk()
                ->assertJsonPath('data.items.0.description', 'Servico sem lazy loading');
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_magic_link_approval_uses_quote_service_and_persists_metadata(): void
    {
        Event::fake([QuoteApproved::class]);

        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $seller = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
            'magic_token' => str_repeat('a', 64),
            'approved_at' => null,
            'client_ip_approval' => null,
            'term_accepted_at' => null,
            'valid_until' => now()->addDays(3),
        ]);

        $this->postJson("/api/v1/quotes/proposal/{$quote->magic_token}/approve", [
            'accept_terms' => true,
        ])->assertOk()
            ->assertJsonPath('message', 'Proposta aprovada com sucesso!');

        $quote->refresh();

        $this->assertSame(Quote::STATUS_APPROVED, $quote->status->value);
        $this->assertNotNull($quote->approved_at);
        $this->assertNotNull($quote->term_accepted_at);
        $this->assertSame('127.0.0.1', $quote->client_ip_approval);
        $this->assertSame('magic_link', $quote->approval_channel);
        $this->assertSame($customer->name, $quote->approved_by_name);

        Event::assertDispatched(QuoteApproved::class, function (QuoteApproved $event) use ($quote, $seller, $customer) {
            return $event->quote->is($quote)
                && $event->user->is($seller)
                && $event->quote->approval_channel === 'magic_link'
                && $event->quote->approved_by_name === $customer->name;
        });
    }

    public function test_magic_link_show_rejects_expired_quote_and_marks_status(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $seller = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
            'magic_token' => str_repeat('c', 64),
            'valid_until' => now()->subDay(),
        ]);

        $this->getJson("/api/v1/quotes/proposal/{$quote->magic_token}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Proposta expirada.');

        $quote->refresh();

        $this->assertSame(Quote::STATUS_EXPIRED, $quote->status->value);
    }

    public function test_magic_link_show_allows_quote_valid_today(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $seller = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
            'magic_token' => str_repeat('e', 64),
            'valid_until' => today(),
        ]);

        $this->getJson("/api/v1/quotes/proposal/{$quote->magic_token}")
            ->assertOk()
            ->assertJsonPath('data.quote_number', $quote->quote_number)
            ->assertJsonPath('data.valid_until', today()->toDateString());

        $this->assertSame(Quote::STATUS_SENT, $quote->fresh()->status->value);
    }

    public function test_magic_link_approval_rejects_expired_quote_without_approving(): void
    {
        Event::fake([QuoteApproved::class]);

        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $seller = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
            'magic_token' => str_repeat('d', 64),
            'approved_at' => null,
            'valid_until' => now()->subDay(),
        ]);

        $this->postJson("/api/v1/quotes/proposal/{$quote->magic_token}/approve", [
            'accept_terms' => true,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Proposta expirada.');

        $quote->refresh();

        $this->assertSame(Quote::STATUS_EXPIRED, $quote->status->value);
        $this->assertNull($quote->approved_at);

        Event::assertNotDispatched(QuoteApproved::class);
    }
}
