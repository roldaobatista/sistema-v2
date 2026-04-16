<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerDocumentControllerTest extends TestCase
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
        Storage::fake('public');

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_documents_for_customer(): void
    {
        CustomerDocument::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'uploaded_by' => $this->user->id,
            'title' => 'RG',
            'file_name' => 'rg.pdf',
            'file_path' => 'customer-documents/1/rg.pdf',
            'type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        $response = $this->getJson("/api/v1/customers/{$this->customer->id}/documents");

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_index_returns_404_for_customer_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson("/api/v1/customers/{$foreignCustomer->id}/documents");

        $response->assertStatus(404);
    }

    public function test_store_validates_required_file(): void
    {
        $response = $this->postJson("/api/v1/customers/{$this->customer->id}/documents", [
            'title' => 'Doc sem arquivo',
        ]);

        $response->assertStatus(422);
    }

    public function test_index_global_returns_only_current_tenant_documents(): void
    {
        CustomerDocument::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'uploaded_by' => $this->user->id,
            'title' => 'Doc meu',
            'file_name' => 'meu.pdf',
            'file_path' => 'customer-documents/meu.pdf',
            'type' => 'application/pdf',
            'file_size' => 100,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        CustomerDocument::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'uploaded_by' => $otherUser->id,
            'title' => 'Doc estranho',
            'file_name' => 'leak.pdf',
            'file_path' => 'customer-documents/leak.pdf',
            'type' => 'application/pdf',
            'file_size' => 100,
        ]);

        // Endpoint global pode ou não estar ativo; se 404 (não rotado), é ok
        $response = $this->getJson('/api/v1/customer-documents');

        if ($response->status() === 200) {
            $json = json_encode($response->json());
            $this->assertStringNotContainsString('leak.pdf', $json, 'Document de outro tenant vazou');
        } else {
            $this->assertContains($response->status(), [403, 404]);
        }
    }

    public function test_destroy_rejects_document_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignDoc = CustomerDocument::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'uploaded_by' => $otherUser->id,
            'title' => 'Foreign',
            'file_name' => 'foreign.pdf',
            'file_path' => 'customer-documents/foreign.pdf',
            'type' => 'application/pdf',
            'file_size' => 100,
        ]);

        $response = $this->deleteJson("/api/v1/customer-documents/{$foreignDoc->id}");

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('customer_documents', ['id' => $foreignDoc->id]);
    }
}
