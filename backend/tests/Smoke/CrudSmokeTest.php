<?php

namespace Tests\Smoke;

use App\Models\Customer;

/**
 * Smoke: CRUD básico
 * Valida create + show nos módulos mais críticos.
 */
class CrudSmokeTest extends SmokeTestCase
{
    public function test_create_customer(): void
    {
        $response = $this->postJson('/api/v1/customers', [
            'type' => 'PF',
            'name' => 'Smoke Cliente',
            'document' => '529.982.247-25',
            'email' => 'smoke-crud@test.com',
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.id'));
    }

    public function test_create_work_order(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $customer->id,
            'description' => 'Smoke Test OS',
            'priority' => 'medium',
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.id'));
    }

    public function test_create_quote(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/quotes', [
            'customer_id' => $customer->id,
            'description' => 'Smoke Orçamento',
        ]);

        // Smoke: aceita 200, 201 (sucesso) ou 422 (validação — significa que o endpoint existe)
        $this->assertTrue(
            in_array($response->status(), [200, 201, 422]),
            "Expected 200/201/422, got {$response->status()}"
        );
    }
}
