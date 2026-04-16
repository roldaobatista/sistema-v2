<?php

namespace Tests\Feature;

use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

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
        $this->otherTenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
        Storage::fake('public');
    }

    public function test_customer_crud_and_tenant_isolation(): void
    {
        $create = $this->postJson('/api/v1/customers', [
            'type' => 'PF',
            'name' => 'Cliente Master',
            'document' => '529.982.247-25',
            'tenant_id' => $this->otherTenant->id,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Cliente Master')
            ->assertJsonPath('data.tenant_id', $this->tenant->id);

        $customerId = (int) $create->json('data.id');

        Customer::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Cliente Outro Tenant',
        ]);

        $this->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/customers/{$customerId}")
            ->assertOk()
            ->assertJsonPath('data.id', $customerId);

        $foreignCustomer = Customer::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->otherTenant->id)
            ->firstOrFail();

        $this->getJson("/api/v1/customers/{$foreignCustomer->id}")
            ->assertStatus(404);

        $this->putJson("/api/v1/customers/{$customerId}", [
            'name' => 'Cliente Master Atualizado',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Cliente Master Atualizado');

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'name' => 'Cliente Master Atualizado',
        ]);
    }

    public function test_customer_delete_returns_409_when_has_dependencies(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $this->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_customer_delete_returns_409_when_has_pending_receivables(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => FinancialStatus::PENDING,
            'amount' => 100.00,
        ]);

        $this->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Não é possivel excluir - cliente possui 1 pendencia(s) financeira(s)');
    }

    public function test_customer_documents_endpoints_require_customer_from_current_tenant(): void
    {
        $foreignCustomer = Customer::factory()->create([
            'tenant_id' => $this->otherTenant->id,
        ]);

        $this->getJson("/api/v1/customers/{$foreignCustomer->id}/documents")
            ->assertStatus(404);

        $this->postJson("/api/v1/customers/{$foreignCustomer->id}/documents", [
            'title' => 'Contrato',
            'file' => UploadedFile::fake()->create('contrato.pdf', 50, 'application/pdf'),
        ])->assertStatus(404);
    }

    public function test_customer_can_upload_and_list_documents_for_same_tenant(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $uploadResponse = $this->postJson("/api/v1/customers/{$customer->id}/documents", [
            'title' => 'Contrato principal',
            'type' => 'contract',
            'file' => UploadedFile::fake()->create('contrato.pdf', 120, 'application/pdf'),
            'notes' => 'Documento de teste',
        ]);

        $uploadResponse->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.title', 'Contrato principal');

        $documentId = (int) $uploadResponse->json('data.id');
        $document = CustomerDocument::findOrFail($documentId);

        Storage::disk('public')->assertExists($document->file_path);

        $this->getJson("/api/v1/customers/{$customer->id}/documents")
            ->assertOk()
            ->assertJsonPath('data.0.id', $documentId)
            ->assertJsonPath('data.0.title', 'Contrato principal');
    }
}
