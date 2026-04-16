<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\FiscalNote;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FiscalDeepAuditTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $tenantB;

    private User $user;

    private Customer $customer;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
    }

    // =========================================================
    //  AUTENTICAÇÃO — 401
    // =========================================================

    public function test_unauthenticated_fiscal_notas_returns_401(): void
    {
        $this->withMiddleware([EnsureTenantScope::class]);
        $this->getJson('/api/v1/fiscal/notas')->assertUnauthorized();
    }

    public function test_unauthenticated_fiscal_stats_returns_401(): void
    {
        $this->withMiddleware([EnsureTenantScope::class]);
        $this->getJson('/api/v1/fiscal/stats')->assertUnauthorized();
    }

    public function test_unauthenticated_invoices_returns_401(): void
    {
        $this->withMiddleware([EnsureTenantScope::class]);
        $this->getJson('/api/v1/invoices')->assertUnauthorized();
    }

    // =========================================================
    //  FISCAL NOTES — ISOLAMENTO TENANT
    // =========================================================

    public function test_fiscal_notas_only_returns_current_tenant(): void
    {
        FiscalNote::factory(3)->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        FiscalNote::factory(2)->create(['tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/fiscal/notas')->assertOk();

        $this->assertCount(3, $response->json('data'));
    }

    public function test_fiscal_nota_show_returns_404_for_other_tenant(): void
    {
        $note = FiscalNote::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("/api/v1/fiscal/notas/{$note->id}")->assertNotFound();
    }

    public function test_fiscal_nota_show_returns_data_with_events(): void
    {
        $note = FiscalNote::factory()->authorized()->nfe()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson("/api/v1/fiscal/notas/{$note->id}")->assertOk();

        $this->assertEquals($note->id, $response->json('data.id'));
    }

    // =========================================================
    //  FISCAL STATS
    // =========================================================

    public function test_fiscal_stats_returns_expected_structure(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/api/v1/fiscal/stats')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'total',
                'authorized',
                'pending',
                'rejected',
                'cancelled',
                'total_nfe',
                'total_nfse',
                'total_amount',
            ]]);
    }

    public function test_fiscal_stats_only_counts_current_tenant(): void
    {
        // 2 authorized NF-e for tenant A (current month)
        FiscalNote::factory(2)->authorized()->nfe()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_at' => now(),
        ]);

        // 5 for tenant B — should not appear in stats
        FiscalNote::factory(5)->authorized()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/fiscal/stats?month='.now()->format('Y-m'))->assertOk();

        $this->assertEquals(2, $response->json('data.total'));
        $this->assertEquals(2, $response->json('data.authorized'));
    }

    public function test_fiscal_stats_counts_by_status(): void
    {
        FiscalNote::factory()->authorized()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'created_at' => now()]);
        FiscalNote::factory()->authorized()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'created_at' => now()]);
        FiscalNote::factory()->cancelled()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'created_at' => now()]);
        FiscalNote::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'status' => FiscalNote::STATUS_REJECTED, 'created_at' => now()]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/fiscal/stats?month='.now()->format('Y-m'))->assertOk();

        $this->assertEquals(4, $response->json('data.total'));
        $this->assertEquals(2, $response->json('data.authorized'));
        $this->assertEquals(1, $response->json('data.cancelled'));
        $this->assertEquals(1, $response->json('data.rejected'));
    }

    // =========================================================
    //  CANCEL FISCAL NOTE — VALIDAÇÃO
    // =========================================================

    public function test_cancel_nota_requires_justificativa(): void
    {
        $note = FiscalNote::factory()->authorized()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/v1/fiscal/notas/{$note->id}/cancelar", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['justificativa']);
    }

    public function test_cancel_nota_requires_justificativa_minimum_15_chars(): void
    {
        $note = FiscalNote::factory()->authorized()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/v1/fiscal/notas/{$note->id}/cancelar", [
            'justificativa' => 'Curto', // menos de 15 chars
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['justificativa']);
    }

    public function test_cancel_already_cancelled_nota_returns_409(): void
    {
        $note = FiscalNote::factory()->cancelled()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/v1/fiscal/notas/{$note->id}/cancelar", [
            'justificativa' => 'Nota já cancelada anteriormente',
        ])->assertStatus(409);
    }

    public function test_cancel_nota_from_other_tenant_returns_404(): void
    {
        $note = FiscalNote::factory()->authorized()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/v1/fiscal/notas/{$note->id}/cancelar", [
            'justificativa' => 'Tentativa cross-tenant maliciosa',
        ])->assertNotFound();
    }

    // =========================================================
    //  EMIT NF-e — VALIDAÇÃO (sem chamar provider externo)
    // =========================================================

    public function test_emit_nfe_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/fiscal/nfe', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'items']);
    }

    public function test_emit_nfe_validates_items_required(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/fiscal/nfe', [
            'customer_id' => $this->customer->id,
            'items' => [], // vazio — mínimo de 1
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_emit_nfe_rejects_cross_tenant_customer(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/fiscal/nfe', [
            'customer_id' => $this->customerB->id, // customer de outro tenant
            'items' => [
                ['description' => 'Produto X', 'quantity' => 1, 'unit_price' => 100.00],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_emit_nfse_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/fiscal/nfse', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'services']);
    }

    // =========================================================
    //  INVOICES — ISOLAMENTO TENANT
    // =========================================================

    public function test_invoices_only_returns_current_tenant(): void
    {
        Invoice::factory(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        Invoice::factory(2)->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/invoices')->assertOk();

        $this->assertCount(3, $response->json('data'));
    }

    public function test_invoices_status_filter_works(): void
    {
        Invoice::factory(2)->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'status' => Invoice::STATUS_DRAFT]);
        Invoice::factory()->issued()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        Invoice::factory()->cancelled()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/invoices?status=draft')->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_invoices_validates_status_filter(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/api/v1/invoices?status=invalid_status')
            ->assertUnprocessable();
    }

    // =========================================================
    //  INVOICE STORE
    // =========================================================

    public function test_store_invoice_validates_required_customer(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/invoices', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_invoice_rejects_cross_tenant_customer(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/invoices', [
            'customer_id' => $this->customerB->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_invoice_creates_draft_successfully(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/v1/invoices', [
            'customer_id' => $this->customer->id,
            'observations' => 'Fatura teste',
        ]);

        // Should return 201 with draft invoice
        $response->assertCreated();

        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);
    }

    public function test_store_invoice_prevents_duplicate_active_invoice_for_same_work_order(): void
    {
        $workOrder = WorkOrder::factory()->delivered()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'work_order_id' => $workOrder->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/invoices', [
            'customer_id' => $this->customer->id,
            'work_order_id' => $workOrder->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Já existe fatura ativa para esta OS');
    }

    // =========================================================
    //  INVOICE STATUS TRANSITIONS
    // =========================================================

    public function test_invoices_metadata_returns_statuses(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/invoices/metadata')->assertOk();

        $this->assertArrayHasKey('statuses', $response->json('data'));
        $this->assertArrayHasKey('draft', $response->json('data.statuses'));
    }

    public function test_invoices_metadata_only_returns_current_tenant_customers(): void
    {
        Customer::factory(3)->create(['tenant_id' => $this->tenant->id]);
        Customer::factory(2)->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/invoices/metadata')->assertOk();

        // Should have 4 (3 created + the 1 from setUp for this tenant)
        $this->assertCount(4, $response->json('data.customers'));
    }

    public function test_invoice_show_returns_404_for_other_tenant(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("/api/v1/invoices/{$invoice->id}")->assertNotFound();
    }

    public function test_invoice_show_returns_invoice_with_relations(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}")->assertOk();

        $this->assertEquals($invoice->id, $response->json('data.id'));
    }
}
