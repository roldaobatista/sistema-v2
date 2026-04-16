<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;

class FinanceIsolationTest extends TenantIsolationTestCase
{
    // ──────────────────────────────────────────────
    // Accounts Receivable
    // ──────────────────────────────────────────────

    public function test_accounts_receivable_index_only_returns_own_tenant(): void
    {
        // Create customer for each tenant
        app()->instance('current_tenant_id', $this->tenantA->id);
        $customerA = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);

        // 3 ARs for tenant A
        AccountReceivable::factory()->count(3)->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $customerA->id,
        ]);

        // 2 ARs for tenant B
        $customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);
        app()->instance('current_tenant_id', $this->tenantB->id);
        AccountReceivable::factory()->count(2)->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $customerB->id,
        ]);

        $this->actingAsTenantA();

        $response = $this->getJson('/api/v1/accounts-receivable');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_cannot_access_other_tenant_account_receivable(): void
    {
        $customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);
        $ar = $this->createForTenantB(AccountReceivable::class, [
            'customer_id' => $customerB->id,
        ]);

        $this->actingAsTenantA();

        $response = $this->getJson("/api/v1/accounts-receivable/{$ar->id}");

        $response->assertNotFound();
    }

    public function test_cannot_update_other_tenant_account_receivable(): void
    {
        $customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);
        $ar = $this->createForTenantB(AccountReceivable::class, [
            'customer_id' => $customerB->id,
        ]);

        $this->actingAsTenantA();

        $response = $this->putJson("/api/v1/accounts-receivable/{$ar->id}", [
            'description' => 'Hacked',
        ]);

        $response->assertNotFound();
    }

    // ──────────────────────────────────────────────
    // Accounts Payable
    // ──────────────────────────────────────────────

    public function test_accounts_payable_index_only_returns_own_tenant(): void
    {
        // 3 APs for tenant A
        app()->instance('current_tenant_id', $this->tenantA->id);
        AccountPayable::factory()->count(3)->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        // 2 APs for tenant B
        app()->instance('current_tenant_id', $this->tenantB->id);
        AccountPayable::factory()->count(2)->create([
            'tenant_id' => $this->tenantB->id,
        ]);

        $this->actingAsTenantA();

        $response = $this->getJson('/api/v1/accounts-payable');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_cannot_access_other_tenant_account_payable(): void
    {
        $ap = $this->createForTenantB(AccountPayable::class);

        $this->actingAsTenantA();

        $response = $this->getJson("/api/v1/accounts-payable/{$ap->id}");

        $response->assertNotFound();
    }

    public function test_cannot_delete_other_tenant_account_payable(): void
    {
        $ap = $this->createForTenantB(AccountPayable::class);

        $this->actingAsTenantA();

        $response = $this->deleteJson("/api/v1/accounts-payable/{$ap->id}");

        $response->assertNotFound();
    }
}
