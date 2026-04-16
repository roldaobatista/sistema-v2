<?php

namespace Tests\Feature\Api;

use App\Enums\QuoteStatus;
use App\Http\Middleware\CheckPermission;
use App\Models\AccountPayableCategory;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Testes profundos quote API e financial API controllers.
 */
class QuoteAndFinancialControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    // ═══ Quote API ═══

    public function test_quotes_index(): void
    {
        Quote::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/quotes');
        $response->assertOk();
    }

    public function test_quote_store(): void
    {
        $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
        $prod = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
            'observations' => 'Orçamento de calibração',
            'valid_until' => now()->addDays(30)->format('Y-m-d'),
            'equipments' => [
                [
                    'equipment_id' => $eq->id,
                    'items' => [
                        [
                            'type' => 'product',
                            'product_id' => $prod->id,
                            'quantity' => 1,
                            'original_price' => 100,
                            'unit_price' => 100,
                        ],
                    ],
                ],
            ],
        ]);
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 201]
    }

    public function test_quote_store_fails_without_customer(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/quotes', [
            'observations' => 'Sem cliente',
        ]);
        $response->assertUnprocessable();
    }

    public function test_quote_show(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/quotes/{$q->id}");
        $response->assertOk();
    }

    public function test_quote_update(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::DRAFT,
        ]);
        $response = $this->actingAs($this->admin)->putJson("/api/v1/quotes/{$q->id}", [
            'observations' => 'Atualizado',
        ]);
        $response->assertOk();
    }

    public function test_quote_filter_by_status(): void
    {
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::SENT,
        ]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/quotes?status=sent');
        $response->assertOk();
    }

    public function test_quote_filter_by_customer(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/quotes?customer_id={$this->customer->id}");
        $response->assertOk();
    }

    public function test_quote_soft_delete(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/quotes/{$q->id}");
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 204]
        $this->assertSoftDeleted($q);
    }

    // ═══ Accounts Receivable API ═══

    public function test_accounts_receivable_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/accounts-receivable');
        $response->assertOk();
    }

    public function test_accounts_receivable_store(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/accounts-receivable', [
            'customer_id' => $this->customer->id,
            'description' => 'Calibração Janeiro',
            'amount' => '1500.00',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_method' => 'pix',
        ]);
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 201]
    }

    public function test_accounts_receivable_filter_by_status(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/accounts-receivable?status=pending');
        $response->assertOk();
    }

    // ═══ Accounts Payable API ═══

    public function test_accounts_payable_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/accounts-payable');
        $response->assertOk();
    }

    public function test_accounts_payable_store(): void
    {
        $category = AccountPayableCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->admin)->postJson('/api/v1/accounts-payable', [
            'description' => 'Aluguel escritório',
            'amount' => '3500.00',
            'category_id' => $category->id,
            'due_date' => now()->addDays(15)->format('Y-m-d'),
        ]);
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 201]
    }

    // ═══ Cross-tenant isolation ═══

    public function test_quote_not_visible_cross_tenant(): void
    {
        $q = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);
        $otherUser->assignRole('admin');

        app()->instance('current_tenant_id', $otherTenant->id);

        $response = $this->actingAs($otherUser)->getJson("/api/v1/quotes/{$q->id}");
        $response->assertNotFound();
    }

    // ═══ Unauthenticated ═══

    public function test_quotes_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/quotes');
        $response->assertUnauthorized();
    }

    public function test_accounts_receivable_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/accounts-receivable');
        $response->assertUnauthorized();
    }

    public function test_accounts_payable_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/accounts-payable');
        $response->assertUnauthorized();
    }
}
