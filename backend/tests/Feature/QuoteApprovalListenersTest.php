<?php

namespace Tests\Feature;

use App\Events\QuoteApproved;
use App\Listeners\CreateAgendaItemOnQuote;
use App\Listeners\HandleQuoteApproval;
use App\Models\AgendaItem;
use App\Models\CrmActivity;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class QuoteApprovalListenersTest extends TestCase
{
    public function test_handle_quote_approval_listener_uses_quote_number_and_seller_as_recipient(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $seller = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        $approver = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'quote_number' => 'ORC-09001',
            'total' => 1250.50,
            'status' => Quote::STATUS_APPROVED,
            'approval_channel' => 'portal',
            'approved_by_name' => 'Cliente Portal',
        ]);

        $listener = app(HandleQuoteApproval::class);
        $listener->handle(new QuoteApproved($quote, $approver));

        $this->assertDatabaseHas('crm_activities', [
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $seller->id,
            'type' => Quote::ACTIVITY_TYPE_APPROVED,
            'title' => 'Orcamento #ORC-09001 aprovado',
            'channel' => 'portal',
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $seller->id,
            'type' => Quote::ACTIVITY_TYPE_APPROVED,
            'title' => 'Orcamento Aprovado',
            'message' => 'O orcamento #ORC-09001 foi aprovado. Aprovado pelo cliente via portal por Cliente Portal.',
        ]);
    }

    public function test_handle_quote_approval_listener_is_idempotent_for_same_quote(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $seller = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        $approver = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'quote_number' => 'ORC-09002',
            'total' => 980.75,
            'status' => Quote::STATUS_APPROVED,
            'approval_channel' => 'magic_link',
            'approved_by_name' => 'Cliente Duplicado',
            'approved_at' => now(),
        ]);

        $listener = app(HandleQuoteApproval::class);
        $event = new QuoteApproved($quote, $approver);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertSame(
            1,
            CrmActivity::query()
                ->where('tenant_id', $tenant->id)
                ->where('type', Quote::ACTIVITY_TYPE_APPROVED)
                ->where('channel', 'magic_link')
                ->where('metadata->quote_id', $quote->id)
                ->count()
        );

        $this->assertSame(
            1,
            Notification::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $seller->id)
                ->where('type', Quote::ACTIVITY_TYPE_APPROVED)
                ->where('data->quote_id', $quote->id)
                ->count()
        );
    }

    public function test_create_agenda_item_on_quote_listener_uses_quote_number_context_and_seller(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $seller = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        $approver = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Alpha',
        ]);

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'quote_number' => 'ORC-05555',
            'total' => 980,
            'status' => Quote::STATUS_APPROVED,
            'approval_channel' => 'magic_link',
            'approved_by_name' => 'Cliente Alpha',
        ]);

        $listener = app(CreateAgendaItemOnQuote::class);
        $listener->handle(new QuoteApproved($quote, $approver));

        $item = AgendaItem::query()
            ->where('tenant_id', $tenant->id)
            ->where('ref_id', $quote->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertSame($seller->id, $item->responsavel_user_id);
        $this->assertStringContainsString('ORC-05555', $item->titulo);
        $this->assertSame('ORC-05555', data_get($item->contexto, 'numero'));
        $this->assertSame('magic_link', data_get($item->contexto, 'approval_channel'));
        $this->assertSame('Cliente Alpha', data_get($item->contexto, 'approved_by_name'));
    }

    public function test_quote_approval_observer_does_not_create_redundant_system_activity_for_quote(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $seller = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'last_contact_at' => null,
        ]);

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'quote_number' => 'ORC-09100',
            'status' => Quote::STATUS_SENT,
            'approved_at' => null,
            'total' => 4321.98,
        ]);

        $quote->update([
            'status' => Quote::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $this->assertSame(
            0,
            CrmActivity::query()
                ->where('tenant_id', $tenant->id)
                ->where('customer_id', $customer->id)
                ->where('type', CrmActivity::TYPE_SYSTEM)
                ->where('metadata->quote_id', $quote->id)
                ->count()
        );

        $this->assertNotNull($customer->fresh()->last_contact_at);
    }
}
