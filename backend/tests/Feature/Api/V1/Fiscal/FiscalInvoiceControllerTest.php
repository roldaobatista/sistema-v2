<?php

namespace Tests\Feature\Api\V1\Fiscal;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\FiscalInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for FiscalInvoiceController.
 *
 * Routes (from routes/api/missing-routes.php):
 *   GET    /api/v1/fiscal-invoices
 *   POST   /api/v1/fiscal-invoices
 *   GET    /api/v1/fiscal-invoices/{fiscalInvoice}
 *   PUT    /api/v1/fiscal-invoices/{fiscalInvoice}
 *   DELETE /api/v1/fiscal-invoices/{fiscalInvoice}
 */
class FiscalInvoiceControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── GET index ──────────────────────────────────────────────

    public function test_can_list_fiscal_invoices(): void
    {
        FiscalInvoice::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/v1/fiscal-invoices');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
    }

    public function test_listing_only_returns_own_tenant_invoices(): void
    {
        $otherTenant = Tenant::factory()->create();
        FiscalInvoice::factory()->create(['tenant_id' => $otherTenant->id]);

        $ownInvoice = FiscalInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'number' => 'OWN-00001',
        ]);

        $response = $this->getJson('/api/v1/fiscal-invoices');

        $response->assertStatus(200);
        $numbers = collect($response->json('data'))->pluck('number')->toArray();
        $this->assertContains('OWN-00001', $numbers);
    }

    // ── POST store ─────────────────────────────────────────────

    public function test_can_create_fiscal_invoice(): void
    {
        $response = $this->postJson('/api/v1/fiscal-invoices', [
            'type' => 'nfse',
            'series' => '1',
            'total' => 1500.00,
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'type' => 'nfse',
            'total' => '1500.00',
        ]);
        $this->assertDatabaseHas('fiscal_invoices', [
            'tenant_id' => $this->tenant->id,
            'type' => 'nfse',
        ]);
    }

    public function test_store_auto_generates_invoice_number_when_not_provided(): void
    {
        $response = $this->postJson('/api/v1/fiscal-invoices', [
            'type' => 'nfe',
            'total' => 500.00,
        ]);

        $response->assertStatus(201);
        $number = $response->json('data.number');
        $this->assertStringStartsWith('TMP-', $number);
    }

    public function test_store_accepts_explicit_invoice_number(): void
    {
        $response = $this->postJson('/api/v1/fiscal-invoices', [
            'number' => 'NF-99999',
            'type' => 'nfe',
            'total' => 200.00,
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['number' => 'NF-99999']);
    }

    public function test_store_validates_invalid_type(): void
    {
        $response = $this->postJson('/api/v1/fiscal-invoices', [
            'type' => 'invalid_type',
            'total' => 100.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_with_valid_customer(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/fiscal-invoices', [
            'type' => 'nfse',
            'customer_id' => $customer->id,
            'total' => 800.00,
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['customer_id' => $customer->id]);
    }

    // ── GET show ───────────────────────────────────────────────

    public function test_can_show_fiscal_invoice(): void
    {
        $invoice = FiscalInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'number' => 'NF-SHOW-001',
            'type' => 'nfse',
        ]);

        $response = $this->getJson("/api/v1/fiscal-invoices/{$invoice->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $invoice->id,
            'number' => 'NF-SHOW-001',
        ]);
    }

    public function test_show_returns_404_for_other_tenant_invoice(): void
    {
        $otherTenant = Tenant::factory()->create();
        $invoice = FiscalInvoice::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson("/api/v1/fiscal-invoices/{$invoice->id}");

        $response->assertStatus(404);
    }

    // ── PUT update ─────────────────────────────────────────────

    public function test_can_update_fiscal_invoice(): void
    {
        $invoice = FiscalInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->putJson("/api/v1/fiscal-invoices/{$invoice->id}", [
            'status' => 'authorized',
            'total' => 9999.00,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('fiscal_invoices', [
            'id' => $invoice->id,
            'status' => 'authorized',
        ]);
    }

    public function test_update_rejects_invalid_type(): void
    {
        $invoice = FiscalInvoice::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->putJson("/api/v1/fiscal-invoices/{$invoice->id}", [
            'type' => 'nf_invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    // ── DELETE destroy ─────────────────────────────────────────

    public function test_can_delete_fiscal_invoice(): void
    {
        $invoice = FiscalInvoice::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->deleteJson("/api/v1/fiscal-invoices/{$invoice->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('fiscal_invoices', ['id' => $invoice->id]);
    }

    public function test_cannot_delete_invoice_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $invoice = FiscalInvoice::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->deleteJson("/api/v1/fiscal-invoices/{$invoice->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('fiscal_invoices', ['id' => $invoice->id]);
    }
}
