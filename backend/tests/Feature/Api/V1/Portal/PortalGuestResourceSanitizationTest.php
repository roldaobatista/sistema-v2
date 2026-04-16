<?php

namespace Tests\Feature\Api\V1\Portal;

use App\Models\Customer;
use App\Models\PortalGuestLink;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Tests\TestCase;

class PortalGuestResourceSanitizationTest extends TestCase
{
    private Tenant $tenant;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->setTenantContext($this->tenant->id);
    }

    public function test_guest_quote_resource_hides_internal_fields(): void
    {
        $seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
            'internal_notes' => 'Somente comercial interno',
            'created_by' => $seller->id,
        ]);

        $guestLink = PortalGuestLink::create([
            'tenant_id' => $this->tenant->id,
            'token' => 'guest-quote-token',
            'entity_type' => Quote::class,
            'entity_id' => $quote->id,
            'expires_at' => now()->addDay(),
            'single_use' => false,
            'created_by' => $seller->id,
        ]);

        $response = $this->getJson("/api/v1/portal/guest/{$guestLink->token}");

        $response->assertOk()
            ->assertJsonPath('data.resource.id', $quote->id)
            ->assertJsonMissingPath('data.resource.tenant_id')
            ->assertJsonMissingPath('data.resource.internal_notes')
            ->assertJsonMissingPath('data.resource.approval_token')
            ->assertJsonMissingPath('data.resource.created_by')
            ->assertJsonMissingPath('data.resource.magic_token');
    }

    public function test_guest_work_order_resource_hides_internal_fields(): void
    {
        $creator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $creator->id,
            'internal_notes' => 'Somente OS interna',
            'technical_report' => 'Diagnostico tecnico interno',
            'signature_ip' => '203.0.113.20',
        ]);
        WorkOrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'type' => WorkOrderItem::TYPE_SERVICE,
            'description' => 'Servico convidado',
            'quantity' => 1,
            'unit_price' => 250,
            'cost_price' => 60,
            'warehouse_id' => 998,
        ]);

        $guestLink = PortalGuestLink::create([
            'tenant_id' => $this->tenant->id,
            'token' => 'guest-work-order-token',
            'entity_type' => WorkOrder::class,
            'entity_id' => $workOrder->id,
            'expires_at' => now()->addDay(),
            'single_use' => false,
            'created_by' => $creator->id,
        ]);

        $response = $this->getJson("/api/v1/portal/guest/{$guestLink->token}");

        $response->assertOk()
            ->assertJsonPath('data.resource.id', $workOrder->id)
            ->assertJsonMissingPath('data.resource.tenant_id')
            ->assertJsonMissingPath('data.resource.created_by')
            ->assertJsonMissingPath('data.resource.internal_notes')
            ->assertJsonMissingPath('data.resource.technical_report')
            ->assertJsonMissingPath('data.resource.signature_ip')
            ->assertJsonMissingPath('data.resource.items.0.tenant_id')
            ->assertJsonMissingPath('data.resource.items.0.cost_price')
            ->assertJsonMissingPath('data.resource.items.0.warehouse_id')
            ->assertJsonPath('data.resource.items.0.description', 'Servico convidado');
    }
}
