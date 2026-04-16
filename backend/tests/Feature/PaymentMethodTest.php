<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Traits\DisablesTenantMiddleware;
use Tests\Traits\SetupTenantUser;

class PaymentMethodTest extends TestCase
{
    use DisablesTenantMiddleware;
    use SetupTenantUser;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->setUpDisablesTenantMiddleware();
        $this->setUpTenantUser();
        setPermissionsTeamId($this->tenant->id);
    }

    public function test_index_returns_only_tenant_methods(): void
    {
        PaymentMethod::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pix',
            'code' => 'pix',
            'sort_order' => 1,
        ]);

        $otherTenant = Tenant::factory()->create();
        PaymentMethod::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Boleto Outro',
            'code' => 'boleto_outro',
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/v1/payment-methods');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Pix'])
            ->assertJsonMissing(['name' => 'Boleto Outro']);
    }

    public function test_store_creates_method_for_current_tenant(): void
    {
        $payload = [
            'name' => 'Cartão de Crédito',
            'code' => 'credit_card',
            'is_active' => true,
            'sort_order' => 2,
        ];

        $response = $this->postJson('/api/v1/payment-methods', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Cartão de Crédito']);

        $this->assertDatabaseHas('payment_methods', [
            'tenant_id' => $this->tenant->id,
            'code' => 'credit_card',
        ]);
    }

    public function test_update_method(): void
    {
        $method = PaymentMethod::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dinheiro',
            'code' => 'cash',
            'sort_order' => 0,
        ]);

        $response = $this->putJson("/api/v1/payment-methods/{$method->id}", [
            'name' => 'Espécie',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Espécie']);

        $this->assertDatabaseHas('payment_methods', [
            'id' => $method->id,
            'name' => 'Espécie',
        ]);
    }

    public function test_destroy_method(): void
    {
        $method = PaymentMethod::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cheque',
            'code' => 'check',
        ]);

        $response = $this->deleteJson("/api/v1/payment-methods/{$method->id}");

        // May return 200 or 204
        $this->assertTrue(in_array($response->status(), [200, 204]),
            "Expected 200 or 204, got {$response->status()}");
    }

    public function test_cannot_update_other_tenant_method(): void
    {
        $otherTenant = Tenant::factory()->create();
        $method = PaymentMethod::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Bitcoin',
            'code' => 'btc',
        ]);

        $response = $this->putJson("/api/v1/payment-methods/{$method->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertNotFound();
    }

    public function test_cannot_destroy_other_tenant_method(): void
    {
        $otherTenant = Tenant::factory()->create();
        $method = PaymentMethod::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Bitcoin',
            'code' => 'btc',
        ]);

        $response = $this->deleteJson("/api/v1/payment-methods/{$method->id}");

        $response->assertNotFound();
    }

    public function test_cannot_create_duplicate_code_in_same_tenant(): void
    {
        PaymentMethod::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pix',
            'code' => 'pix',
        ]);

        $response = $this->postJson('/api/v1/payment-methods', [
            'name' => 'Pix 2',
            'code' => 'pix',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_can_create_duplicate_code_in_different_tenants(): void
    {
        $otherTenant = Tenant::factory()->create();
        PaymentMethod::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Pix',
            'code' => 'pix',
        ]);

        $response = $this->postJson('/api/v1/payment-methods', [
            'name' => 'Pix',
            'code' => 'pix',
        ]);

        $response->assertCreated();
    }
}
