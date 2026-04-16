<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_invoices(): void
    {
        Invoice::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        Invoice::factory()->count(4)->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk()->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertIsArray($data);

        foreach ($data as $invoice) {
            $this->assertEquals(
                $this->tenant->id,
                $invoice['tenant_id'] ?? null,
                'Invoice de outro tenant vazou na listagem'
            );
        }
    }

    public function test_show_returns_404_for_cross_tenant_invoice(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = Invoice::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->getJson("/api/v1/invoices/{$foreign->id}");

        $response->assertStatus(404);
    }

    public function test_store_validates_required_customer_id(): void
    {
        $response = $this->postJson('/api/v1/invoices', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_rejects_customer_from_other_tenant(): void
    {
        // Security check — customer deve existir APENAS no tenant atual (exists com where)
        $otherTenant = Tenant::factory()->create();
        $foreignCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/invoices', [
            'customer_id' => $foreignCustomer->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_metadata_endpoint_returns_customer_lookup(): void
    {
        $response = $this->getJson('/api/v1/invoices/metadata');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_update_returns_404_for_cross_tenant_invoice(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = Invoice::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->putJson("/api/v1/invoices/{$foreign->id}", [
            'customer_id' => $this->customer->id,
        ]);

        // Nao pode aceitar update em invoice de outro tenant
        $this->assertNotEquals(200, $response->status(), 'Cross-tenant update de Invoice foi permitido — SECURITY P0');
        $this->assertContains($response->status(), [403, 404, 422]);
    }
}
