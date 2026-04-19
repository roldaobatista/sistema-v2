<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');

    }

    public function test_index_returns_customers(): void
    {
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/customers');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_store_creates_customer(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/customers', [
                'name' => 'Empresa Teste Ltda',
                'email' => 'contato@empresateste.com',
                'phone' => '11999887766',
                'type' => 'PJ',
            ]);

        $response->assertCreated();
    }

    public function test_show_returns_customer(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertOk();
    }

    public function test_update_modifies_customer(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/customers/{$customer->id}", [
                'name' => 'Nome Atualizado',
            ]);

        $response->assertOk();
    }

    public function test_destroy_soft_deletes_customer(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_store_validation_requires_name(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/customers', []);

        $response->assertUnprocessable();
    }

    public function test_show_returns_404(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/customers/99999');

        $response->assertNotFound();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/customers');
        $response->assertUnauthorized();
    }

    public function test_index_search_by_name(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kalibrium Test']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/customers?search=Kalibrium');

        $response->assertOk();
    }

    public function test_index_search_matches_document_without_mask(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Documento',
            'document' => '12.345.678/0001-90',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/customers?search=12345678000190');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertSame('Cliente Documento', $response->json('data.0.name'));
    }

    public function test_cross_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);
        $otherUser->assignRole('admin');

        Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $otherTenant->id);
        $response = $this->actingAs($otherUser)
            ->getJson('/api/v1/customers');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }
}
