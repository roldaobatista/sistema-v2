<?php

namespace Tests\Feature\Api\V1\SupplierPortal;

use App\Models\PortalGuestLink;
use App\Models\PurchaseQuotation;
use App\Models\PurchaseQuotationItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierPortalControllerTest extends TestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        // Since it's a guest portal controller, we do not bypass EnsureTenantScope manually here if we don't auth.
        // But the link defines the tenant access implicitly via the entity's tenant_id. Still we can keep standard setup.
        $this->tenant = Tenant::factory()->create();
    }

    private function createPurchaseQuotationWithLink(
        string $status = 'pending',
        $validUntil = null,
        bool $expiredLink = false,
        bool $singleUse = true,
    ): array {
        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

        $quotation = PurchaseQuotation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'status' => $status,
            'valid_until' => $validUntil ?? now()->addDays(7),
        ]);

        PurchaseQuotationItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_quotation_id' => $quotation->id,
            'quantity' => 10,
        ]);

        $token = Str::random(32);

        $link = PortalGuestLink::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => PurchaseQuotation::class,
            'entity_id' => $quotation->id,
            'token' => $token,
            'expires_at' => $expiredLink ? now()->subDay() : now()->addDays(7),
            'single_use' => $singleUse,
        ]);

        return [$quotation, $token, $link];
    }

    public function test_show_quotation_returns_valid_data(): void
    {
        [$quotation, $token] = $this->createPurchaseQuotationWithLink();

        $response = $this->getJson("/api/v1/supplier-portal/quotations/{$token}");

        $response->assertOk()
            ->assertJsonPath('data.type', 'PurchaseQuotation')
            ->assertJsonPath('data.resource.id', $quotation->id);
    }

    public function test_show_quotation_returns_404_for_invalid_or_expired_token(): void
    {
        // 1. Invalid Token
        $response1 = $this->getJson('/api/v1/supplier-portal/quotations/invalid-token-123');
        $response1->assertNotFound();

        // 2. Expired Token
        [$quotation, $token] = $this->createPurchaseQuotationWithLink('pending', null, true);
        $response2 = $this->getJson("/api/v1/supplier-portal/quotations/{$token}");
        $response2->assertNotFound();
    }

    public function test_show_quotation_returns_422_for_wrong_entity_type(): void
    {
        $token = Str::random(32);
        PortalGuestLink::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => WorkOrder::class, // Wrong entity
            'entity_id' => 1,
            'token' => $token,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->getJson("/api/v1/supplier-portal/quotations/{$token}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tipo de entidade invalida para o portal do fornecedor.');
    }

    public function test_answer_quotation_can_submit_prices(): void
    {
        [$quotation, $token, $link] = $this->createPurchaseQuotationWithLink();
        $item = $quotation->items()->first();

        $payload = [
            'action' => 'submit',
            'notes' => 'Pricing looks good',
            'items' => [
                [
                    'id' => $item->id,
                    'unit_price' => 25.50,
                ],
            ],
        ];

        $response = $this->postJson("/api/v1/supplier-portal/quotations/{$token}/answer", $payload);

        $response->assertOk();
        $this->assertDatabaseHas('purchase_quotations', [
            'id' => $quotation->id,
            'status' => 'answered',
            'notes' => 'Pricing looks good',
            'total_amount' => 255.00, // 10 qty * 25.50
        ]);

        $this->assertDatabaseHas('purchase_quotation_items', [
            'id' => $item->id,
            'unit_price' => 25.50,
            'total' => 255.00,
        ]);

        // Link should be consumed if singular
        $this->assertNotNull($link->fresh()->consumed_at);
    }

    public function test_answer_quotation_can_reject_quotation(): void
    {
        [$quotation, $token, $link] = $this->createPurchaseQuotationWithLink();

        $payload = [
            'action' => 'reject',
            'notes' => 'We don\'t have stock',
        ];

        $response = $this->postJson("/api/v1/supplier-portal/quotations/{$token}/answer", $payload);

        $response->assertOk();
        $this->assertDatabaseHas('purchase_quotations', [
            'id' => $quotation->id,
            'status' => 'rejected',
            'notes' => 'We don\'t have stock',
        ]);
    }

    public function test_answer_quotation_validates_payload(): void
    {
        [$quotation, $token] = $this->createPurchaseQuotationWithLink();

        // 1. Missing action
        $response1 = $this->postJson("/api/v1/supplier-portal/quotations/{$token}/answer", []);
        $response1->assertStatus(422)->assertJsonValidationErrors(['action']);

        // 2. Action submit missing items
        $response2 = $this->postJson("/api/v1/supplier-portal/quotations/{$token}/answer", ['action' => 'submit']);
        $response2->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    public function test_answer_quotation_rejects_items_from_another_quotation(): void
    {
        [$quotation, $token] = $this->createPurchaseQuotationWithLink();
        [$otherQuotation] = $this->createPurchaseQuotationWithLink();
        $foreignItem = $otherQuotation->items()->first();

        $response = $this->postJson("/api/v1/supplier-portal/quotations/{$token}/answer", [
            'action' => 'submit',
            'items' => [
                [
                    'id' => $foreignItem->id,
                    'unit_price' => 99.90,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.id']);

        $this->assertDatabaseHas('purchase_quotations', [
            'id' => $quotation->id,
            'status' => 'pending',
        ]);
    }

    public function test_answer_quotation_blocks_already_answered_quotations(): void
    {
        [$quotation, $token] = $this->createPurchaseQuotationWithLink('answered');

        $response = $this->postJson("/api/v1/supplier-portal/quotations/{$token}/answer", ['action' => 'reject']);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Esta cotacao não pode mais ser respondida.');
    }

    public function test_answer_quotation_blocks_expired_quotations(): void
    {
        // Entity valid_until is expired
        [$quotation, $token] = $this->createPurchaseQuotationWithLink('pending', now()->subDay());

        $response = $this->postJson("/api/v1/supplier-portal/quotations/{$token}/answer", ['action' => 'reject']);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Esta cotacao esta expirada.');
    }

    public function test_non_single_use_link_is_not_consumed_permanently(): void
    {
        [, , $link] = $this->createPurchaseQuotationWithLink('pending', null, false, false);

        $link->consume();

        $freshLink = $link->fresh();
        $this->assertTrue($freshLink->isValid());
        $this->assertNull($freshLink->consumed_at);
        $this->assertNotNull($freshLink->used_at);
    }
}
