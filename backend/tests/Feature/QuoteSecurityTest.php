<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\QuoteTag;
use App\Models\QuoteTemplate;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class QuoteSecurityTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $user;

    private User $viewOnlyUser;

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
        $this->otherTenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->viewOnlyUser = User::factory()->create([
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

        Permission::findOrCreate('quotes.quote.view', 'web');
        Permission::findOrCreate('quotes.quote.create', 'web');
        Permission::findOrCreate('quotes.quote.update', 'web');
        Permission::findOrCreate('quotes.quote.delete', 'web');
        Permission::findOrCreate('quotes.quote.send', 'web');

        $this->user->givePermissionTo([
            'quotes.quote.view',
            'quotes.quote.create',
            'quotes.quote.update',
            'quotes.quote.delete',
            'quotes.quote.send',
        ]);

        $this->viewOnlyUser->givePermissionTo('quotes.quote.view');

        Sanctum::actingAs($this->user, ['*']);
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
            'quantity' => 1,
            'original_price' => 500,
            'unit_price' => 500,
            'discount_percentage' => 0,
            'sort_order' => 0,
        ]);

        return $quote;
    }

    // ── 1.1 updateItem/removeItem: abort quando quote é null ──────────

    public function test_update_item_returns_404_when_equipment_has_no_quote(): void
    {
        // Criar equipment órfão (sem quote válido)
        $orphanEquipment = QuoteEquipment::create([
            'tenant_id' => $this->tenant->id,
            'quote_id' => 0,
            'equipment_id' => $this->equipment->id,
            'description' => 'Órfão',
            'sort_order' => 0,
        ]);

        $item = $orphanEquipment->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 1,
            'original_price' => 100,
            'unit_price' => 100,
            'discount_percentage' => 0,
            'sort_order' => 0,
        ]);

        $this->putJson("/api/v1/quote-items/{$item->id}", [
            'quantity' => 5,
            'unit_price' => 200,
        ])->assertStatus(404);
    }

    public function test_remove_item_returns_404_when_equipment_has_no_quote(): void
    {
        $orphanEquipment = QuoteEquipment::create([
            'tenant_id' => $this->tenant->id,
            'quote_id' => 0,
            'equipment_id' => $this->equipment->id,
            'description' => 'Órfão',
            'sort_order' => 0,
        ]);

        $item = $orphanEquipment->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 1,
            'original_price' => 100,
            'unit_price' => 100,
            'discount_percentage' => 0,
            'sort_order' => 0,
        ]);

        $this->deleteJson("/api/v1/quote-items/{$item->id}")
            ->assertStatus(404);
    }

    public function test_update_item_authorizes_correctly_with_valid_quote(): void
    {
        $quote = $this->makeQuoteWithItems();
        $item = $quote->equipments->first()->items->first();

        $this->putJson("/api/v1/quote-items/{$item->id}", [
            'quantity' => 3,
            'unit_price' => 600,
        ])->assertOk();
    }

    // ── 1.2 addPhoto: validar ownership do equipment ──────────────────

    public function test_add_photo_rejects_equipment_from_another_quote(): void
    {
        Storage::fake('public');

        $quote1 = $this->makeQuoteWithItems();
        $quote2 = $this->makeQuoteWithItems();

        $equipmentFromQuote2 = $quote2->equipments->first();

        // FormRequest valida ownership retornando 422, ou controller retorna 403
        $response = $this->postJson("/api/v1/quotes/{$quote1->id}/photos", [
            'file' => UploadedFile::fake()->image('test.jpg'),
            'quote_equipment_id' => $equipmentFromQuote2->id,
        ]);

        $this->assertTrue(
            in_array($response->status(), [403, 422]),
            "Expected 403 or 422, got {$response->status()}"
        );
    }

    public function test_add_photo_accepts_equipment_from_same_quote(): void
    {
        Storage::fake('public');

        $quote = $this->makeQuoteWithItems();
        $equipment = $quote->equipments->first();

        $this->postJson("/api/v1/quotes/{$quote->id}/photos", [
            'file' => UploadedFile::fake()->image('test.jpg'),
            'quote_equipment_id' => $equipment->id,
        ])->assertStatus(201);
    }

    // ── 1.3 destroyTag/destroyTemplate: tenant isolation ──────────────

    public function test_destroy_tag_from_other_tenant_returns_404(): void
    {
        $otherTag = QuoteTag::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Tag Alheia',
            'color' => '#FF0000',
        ]);

        $this->deleteJson("/api/v1/quote-tags/{$otherTag->id}")
            ->assertStatus(404);
    }

    public function test_destroy_template_from_other_tenant_returns_404(): void
    {
        $otherTemplate = QuoteTemplate::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Template Alheio',
            'is_active' => true,
        ]);

        $this->deleteJson("/api/v1/quote-templates/{$otherTemplate->id}")
            ->assertStatus(404);
    }

    public function test_destroy_own_tag_succeeds(): void
    {
        $tag = QuoteTag::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Minha Tag',
            'color' => '#00FF00',
        ]);

        $this->deleteJson("/api/v1/quote-tags/{$tag->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('quote_tags', ['id' => $tag->id]);
    }

    // ── 1.4 destroy: bloquear quando tem ServiceCall vinculado ────────

    public function test_destroy_quote_blocked_when_service_call_linked(): void
    {
        $quote = $this->makeQuoteWithItems();

        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'quote_id' => $quote->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->deleteJson("/api/v1/quotes/{$quote->id}")
            ->assertStatus(409);
    }

    public function test_destroy_quote_succeeds_when_no_linked_records(): void
    {
        $quote = $this->makeQuoteWithItems();

        $this->deleteJson("/api/v1/quotes/{$quote->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
    }

    // ── 1.5 magic_token: não expor para user sem permissão send ───────

    public function test_magic_token_hidden_for_view_only_user(): void
    {
        Sanctum::actingAs($this->viewOnlyUser, ['*']);

        $quote = $this->makeQuoteWithItems([
            'magic_token' => 'secret-token-123',
            'status' => QuoteStatus::SENT->value,
        ]);

        $response = $this->getJson("/api/v1/quotes/{$quote->id}")
            ->assertOk();

        $data = $response->json('data');
        $this->assertArrayNotHasKey('magic_token', $data);
    }

    public function test_magic_token_visible_for_user_with_send_permission(): void
    {
        $quote = $this->makeQuoteWithItems([
            'magic_token' => 'secret-token-123',
            'status' => QuoteStatus::SENT->value,
        ]);

        $response = $this->getJson("/api/v1/quotes/{$quote->id}")
            ->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('magic_token', $data);
        $this->assertEquals('secret-token-123', $data['magic_token']);
    }
}
