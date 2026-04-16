<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Lookups\PaymentTerm;
use App\Models\Lookups\QuoteSource;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteLookupCompatibilityTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Equipment $equipment;

    private Product $product;

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
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_store_accepts_lookup_source_name_from_form(): void
    {
        QuoteSource::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Google Ads',
            'slug' => 'google-ads',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/quotes', $this->quotePayload([
            'source' => 'Google Ads',
        ]));

        $response->assertStatus(201);

        $this->assertDatabaseHas('quotes', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'source' => 'Google Ads',
        ]);
    }

    public function test_store_accepts_lookup_payment_terms_name_and_detail(): void
    {
        PaymentTerm::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Entrada + 30/60 dias',
            'slug' => 'entrada-30-60-dias',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/quotes', $this->quotePayload([
            'payment_terms' => 'Entrada + 30/60 dias',
            'payment_terms_detail' => '50% entrada e saldo em 60 dias',
        ]));

        $response->assertStatus(201);

        $this->assertDatabaseHas('quotes', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'payment_terms' => 'Entrada + 30/60 dias',
            'payment_terms_detail' => '50% entrada e saldo em 60 dias',
        ]);
    }

    public function test_update_accepts_lookup_slugs_for_source_and_payment_terms(): void
    {
        QuoteSource::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Indicacao Parceiro',
            'slug' => 'indicacao-parceiro',
            'is_active' => true,
        ]);

        PaymentTerm::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sob Consulta',
            'slug' => 'sob-consulta',
            'is_active' => true,
        ]);

        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_DRAFT,
        ]);

        $this->putJson("/api/v1/quotes/{$quote->id}", [
            'source' => 'indicacao-parceiro',
            'payment_terms' => 'sob-consulta',
            'payment_terms_detail' => 'Prazo definido apos vistoria tecnica',
        ])->assertOk();

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'source' => 'indicacao-parceiro',
            'payment_terms' => 'sob-consulta',
            'payment_terms_detail' => 'Prazo definido apos vistoria tecnica',
        ]);
    }

    public function test_store_keeps_legacy_source_value_compatible(): void
    {
        $response = $this->postJson('/api/v1/quotes', $this->quotePayload([
            'source' => 'prospeccao',
        ]));

        $response->assertStatus(201);

        $this->assertDatabaseHas('quotes', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'source' => 'prospeccao',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function quotePayload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'valid_until' => now()->addDays(10)->toDateString(),
            'equipments' => [
                [
                    'equipment_id' => $this->equipment->id,
                    'description' => 'Equipamento de teste',
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
        ], $overrides);
    }
}
