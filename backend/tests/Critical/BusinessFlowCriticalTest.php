<?php

namespace Tests\Critical;

use App\Events\CustomerCreated;
use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Support\Facades\Schema;

class BusinessFlowCriticalTest extends CriticalTestCase
{
    public function test_customer_work_order_receivable_flow_keeps_state_and_audit_consistent(): void
    {
        $customerResponse = $this->postJson('/api/v1/customers', [
            'type' => 'PJ',
            'name' => 'Cliente Fluxo Crítico',
            'email' => 'cliente.fluxo@example.com',
            'contacts' => [
                [
                    'name' => 'Contato Principal',
                    'email' => 'contato.fluxo@example.com',
                    'phone' => '65999999999',
                    'is_primary' => true,
                ],
            ],
        ]);

        $customerResponse->assertCreated();
        $customerId = (int) $customerResponse->json('data.id');

        $workOrderResponse = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $customerId,
            'description' => 'Calibração inicial da balança',
            'priority' => 'high',
            'items' => [
                [
                    'type' => 'service',
                    'description' => 'Serviço de calibração',
                    'quantity' => 1,
                    'unit_price' => 250,
                ],
            ],
        ]);

        $workOrderResponse->assertCreated();
        $workOrderId = (int) $workOrderResponse->json('data.id');

        $receivableResponse = $this->postJson('/api/v1/accounts-receivable/generate-from-os', [
            'work_order_id' => $workOrderId,
            'due_date' => now()->addDays(10)->toDateString(),
            'payment_method' => 'pix',
        ]);

        $receivableResponse->assertCreated()
            ->assertJsonPath('data.customer_id', $customerId)
            ->assertJsonPath('data.work_order_id', $workOrderId);

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Fluxo Crítico',
        ]);
        $this->assertDatabaseHas('customer_contacts', [
            'customer_id' => $customerId,
            'tenant_id' => $this->tenant->id,
            'email' => 'contato.fluxo@example.com',
        ]);
        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrderId,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customerId,
        ]);
        $this->assertDatabaseHas('accounts_receivable', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customerId,
            'work_order_id' => $workOrderId,
            'payment_method' => 'pix',
            'amount' => 250,
        ]);
        if (Schema::hasTable('work_order_status_histories')) {
            $this->assertDatabaseHas('work_order_status_histories', [
                'tenant_id' => $this->tenant->id,
                'work_order_id' => $workOrderId,
                'to_status' => 'open',
            ]);
        }

        $auditTrailResponse = $this->getJson("/api/v1/work-orders/{$workOrderId}/audit-trail");

        $auditTrailResponse->assertOk();

        $entries = collect($auditTrailResponse->json('data'));

        $this->assertTrue(
            $entries->contains(fn (array $entry): bool => $entry['action'] === 'status_changed'
                && (int) $entry['entity_id'] === $workOrderId
                && ($entry['new_values']['status'] ?? null) === 'open'
            ),
            'A trilha de auditoria da OS não retornou o evento inicial de status.'
        );
    }

    public function test_customer_store_rolls_back_everything_when_event_listener_fails(): void
    {
        $customerEmail = 'rollback.customer@example.com';
        $contactEmail = 'rollback.contact@example.com';

        app('events')->listen(CustomerCreated::class, function (): void {
            throw new \RuntimeException('Forced listener failure');
        });

        $response = $this->postJson('/api/v1/customers', [
            'type' => 'PJ',
            'name' => 'Cliente Rollback',
            'email' => $customerEmail,
            'contacts' => [
                [
                    'name' => 'Contato Rollback',
                    'email' => $contactEmail,
                    'phone' => '65988887777',
                    'is_primary' => true,
                ],
            ],
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Erro interno ao criar cliente.');

        $this->assertFalse(Customer::query()->where('email', $customerEmail)->exists());
        $this->assertFalse(CustomerContact::query()->where('email', $contactEmail)->exists());
    }

    public function test_customer_index_returns_paginated_contract_with_legacy_meta_fields(): void
    {
        Customer::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/v1/customers?per_page=2&page=1');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
                'current_page',
                'last_page',
                'per_page',
                'total',
                'from',
                'to',
            ])
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('last_page', 2);
    }
}
