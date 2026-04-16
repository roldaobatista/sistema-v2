<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentMethodControllerTest extends TestCase
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
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_payment_methods(): void
    {
        PaymentMethod::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $otherTenant = Tenant::factory()->create();
        PaymentMethod::factory()->count(5)->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson('/api/v1/payment-methods');

        $response->assertOk();
        $data = $response->json('data') ?? $response->json();
        $this->assertIsArray($data);

        foreach ($data as $method) {
            if (isset($method['tenant_id'])) {
                $this->assertEquals(
                    $this->tenant->id,
                    $method['tenant_id'],
                    'PaymentMethod de outro tenant vazou'
                );
            }
        }
    }

    public function test_store_validates_required_name_and_code(): void
    {
        $response = $this->postJson('/api/v1/payment-methods', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code']);
    }

    public function test_store_rejects_duplicate_name_in_same_tenant(): void
    {
        PaymentMethod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dinheiro',
            'code' => 'CASH',
        ]);

        $response = $this->postJson('/api/v1/payment-methods', [
            'name' => 'Dinheiro',
            'code' => 'CASH2',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_duplicate_code_in_same_tenant(): void
    {
        PaymentMethod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cartao Credito',
            'code' => 'CC',
        ]);

        $response = $this->postJson('/api/v1/payment-methods', [
            'name' => 'Cartao Credito Visa',
            'code' => 'CC', // duplicado
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_store_creates_payment_method(): void
    {
        $response = $this->postJson('/api/v1/payment-methods', [
            'name' => 'PIX Teste',
            'code' => 'PIX_TEST',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payment_methods', [
            'tenant_id' => $this->tenant->id,
            'code' => 'PIX_TEST',
            'name' => 'PIX Teste',
        ]);
    }

    public function test_destroy_returns_non_success_for_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = PaymentMethod::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->deleteJson("/api/v1/payment-methods/{$foreign->id}");

        $this->assertNotEquals(200, $response->status(), 'Cross-tenant delete foi permitido — P0');
        $this->assertNotEquals(204, $response->status(), 'Cross-tenant delete foi permitido — P0');
        $this->assertContains($response->status(), [403, 404]);
    }
}
