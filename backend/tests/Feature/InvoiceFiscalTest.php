<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Invoice CRUD + Fiscal NF-e/NFS-e endpoints.
 */
class InvoiceFiscalTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_list_invoices(): void
    {
        $response = $this->getJson('/api/v1/invoices');
        $response->assertOk();
    }

    public function test_invoice_metadata(): void
    {
        $response = $this->getJson('/api/v1/invoices/metadata');
        $response->assertOk();
    }

    public function test_create_invoice(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson('/api/v1/invoices', [
            'work_order_id' => $wo->id,
            'customer_id' => $this->customer->id,
            'amount' => 1500.00,
        ]);
        $response->assertCreated();
    }

    public function test_create_invoice_from_work_order_updates_linked_quote_status(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_IN_EXECUTION,
        ]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_id' => $quote->id,
            'status' => WorkOrder::STATUS_DELIVERED,
            'total' => 1500.00,
        ]);

        $response = $this->postJson('/api/v1/invoices', [
            'work_order_id' => $wo->id,
            'customer_id' => $this->customer->id,
            'amount' => 1500.00,
        ]);

        $response->assertCreated();
        $this->assertSame(Quote::STATUS_INVOICED, $quote->fresh()->status->value ?? $quote->fresh()->status);
    }

    public function test_cancel_last_invoice_reverts_linked_quote_status(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_INVOICED,
        ]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_id' => $quote->id,
            'status' => WorkOrder::STATUS_INVOICED,
            'total' => 1500.00,
        ]);

        $invoiceResponse = $this->postJson('/api/v1/invoices', [
            'work_order_id' => $wo->id,
            'customer_id' => $this->customer->id,
            'amount' => 1500.00,
        ]);

        $invoiceResponse->assertCreated();
        $invoiceId = $invoiceResponse->json('data.id');

        $this->putJson("/api/v1/invoices/{$invoiceId}", [
            'status' => 'cancelled',
        ])->assertOk();

        $this->assertSame(WorkOrder::STATUS_DELIVERED, $wo->fresh()->status);
        $this->assertSame(Quote::STATUS_IN_EXECUTION, $quote->fresh()->status->value ?? $quote->fresh()->status);
    }

    public function test_fiscal_notas_index(): void
    {
        $response = $this->getJson('/api/v1/fiscal/notas');
        $response->assertOk();
    }

    public function test_emitir_nfse_requires_data(): void
    {
        $response = $this->postJson('/api/v1/fiscal/nfse', []);
        $response->assertStatus(422);
    }

    public function test_cancelar_nota_inexistente_returns_404(): void
    {
        $response = $this->postJson('/api/v1/fiscal/notas/99999/cancelar', [
            'justificativa' => 'Teste de cancelamento',
        ]);
        $response->assertStatus(404);
    }
}
