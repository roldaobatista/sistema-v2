<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class QuoteAuthorizationTest extends TestCase
{
    private Tenant $tenant;

    private User $restrictedUser;

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
        $this->restrictedUser = User::factory()->create([
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
        $this->restrictedUser->givePermissionTo('quotes.quote.view');

        Sanctum::actingAs($this->restrictedUser, ['*']);
    }

    private function makeQuote(string $status): Quote
    {
        return Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->restrictedUser->id,
            'status' => $status,
        ]);
    }

    public function test_send_requires_send_permission_even_without_permission_middleware(): void
    {
        $quote = $this->makeQuote(Quote::STATUS_INTERNALLY_APPROVED);

        $this->postJson("/api/v1/quotes/{$quote->id}/send")
            ->assertForbidden();
    }

    public function test_internal_approve_requires_internal_approve_permission(): void
    {
        $quote = $this->makeQuote(Quote::STATUS_DRAFT);

        $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve")
            ->assertForbidden();
    }

    public function test_convert_requires_convert_permission(): void
    {
        $quote = $this->makeQuote(Quote::STATUS_APPROVED);

        $this->postJson("/api/v1/quotes/{$quote->id}/convert-to-os")
            ->assertForbidden();
    }

    public function test_equipment_mutation_requires_update_permission(): void
    {
        $quote = $this->makeQuote(Quote::STATUS_DRAFT);

        $this->postJson("/api/v1/quotes/{$quote->id}/equipments", [
            'equipment_id' => $this->equipment->id,
        ])->assertForbidden();
    }

    public function test_send_email_requires_send_permission(): void
    {
        $quote = $this->makeQuote(Quote::STATUS_SENT);

        $this->postJson("/api/v1/quotes/{$quote->id}/email", [
            'recipient_email' => 'cliente@example.com',
            'recipient_name' => 'Cliente',
            'message' => 'Teste',
        ])->assertForbidden();
    }

    public function test_store_with_fixed_discount_requires_discount_permission(): void
    {
        $this->restrictedUser->givePermissionTo('quotes.quote.create');

        $this->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
            'discount_amount' => 25,
            'equipments' => [
                [
                    'equipment_id' => $this->equipment->id,
                    'items' => [
                        [
                            'type' => 'product',
                            'product_id' => $this->product->id,
                            'quantity' => 1,
                            'original_price' => 100,
                            'unit_price' => 100,
                        ],
                    ],
                ],
            ],
        ])->assertForbidden();
    }

    public function test_update_with_fixed_discount_requires_discount_permission(): void
    {
        $this->restrictedUser->givePermissionTo('quotes.quote.update');
        $quote = $this->makeQuote(Quote::STATUS_DRAFT);

        $this->putJson("/api/v1/quotes/{$quote->id}", [
            'discount_amount' => 15,
        ])->assertForbidden();
    }
}
