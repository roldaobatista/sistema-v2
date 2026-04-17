<?php

namespace Tests\Unit\FormRequests;

use App\Http\Requests\Agenda\StoreAgendaItemRequest;
use App\Http\Requests\Crm\StoreCrmDealRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Equipment\StoreEquipmentRequest;
use App\Http\Requests\Financial\StoreAccountPayableRequest;
use App\Http\Requests\Financial\StoreExpenseRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Quote\StoreQuoteRequest;
use App\Http\Requests\ServiceCall\StoreServiceCallRequest;
use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FormRequestValidationTest extends TestCase
{
    // ── StoreWorkOrderRequest ──

    public function test_work_order_store_requires_customer_id(): void
    {
        $request = new StoreWorkOrderRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('customer_id', $rules);
    }

    public function test_work_order_store_has_description_rule(): void
    {
        $request = new StoreWorkOrderRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('description', $rules);
    }

    // ── StoreCustomerRequest ──

    public function test_customer_store_requires_name(): void
    {
        $request = new StoreCustomerRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
    }

    public function test_customer_store_validates_email(): void
    {
        $request = new StoreCustomerRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('email', $rules);
        $emailRules = is_string($rules['email']) ? $rules['email'] : implode('|', (array) $rules['email']);
        $this->assertStringContainsString('email', $emailRules);
    }

    // ── StoreQuoteRequest ──

    public function test_quote_store_requires_customer_id(): void
    {
        $request = new StoreQuoteRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('customer_id', $rules);
    }

    // ── StoreEquipmentRequest ──

    public function test_equipment_store_requires_customer_id(): void
    {
        $request = new StoreEquipmentRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('customer_id', $rules);
    }

    public function test_equipment_store_requires_type(): void
    {
        $request = new StoreEquipmentRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('type', $rules);
    }

    // ── StoreAccountPayableRequest ──

    public function test_payable_store_requires_amount(): void
    {
        $request = new StoreAccountPayableRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('amount', $rules);
    }

    public function test_payable_store_requires_due_date(): void
    {
        $request = new StoreAccountPayableRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('due_date', $rules);
    }

    // ── StoreAgendaItemRequest ──

    public function test_agenda_store_requires_titulo(): void
    {
        $request = new StoreAgendaItemRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('title', $rules);
    }

    // ── StoreCrmDealRequest ──

    public function test_deal_store_requires_customer_id(): void
    {
        $request = new StoreCrmDealRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('customer_id', $rules);
    }

    public function test_deal_store_requires_pipeline_id(): void
    {
        $request = new StoreCrmDealRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('pipeline_id', $rules);
    }

    // ── StoreExpenseRequest ──

    public function test_expense_store_requires_amount(): void
    {
        $request = new StoreExpenseRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('amount', $rules);
    }

    // ── StoreProductRequest ──

    public function test_product_store_requires_name(): void
    {
        $request = new StoreProductRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
    }

    // ── StoreServiceCallRequest ──

    public function test_service_call_store_requires_customer_id(): void
    {
        $request = new StoreServiceCallRequest;
        $request->setUserResolver(fn () => $this->createAuthenticatedUser());
        $rules = $request->rules();

        $this->assertArrayHasKey('customer_id', $rules);
    }

    // ── Authorization ──

    public function test_work_order_store_authorizes_authenticated_user(): void
    {
        $user = $this->createAuthenticatedUser();
        $user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'os.work_order.create', 'guard_name' => 'web'])
        );
        $request = new StoreWorkOrderRequest;
        $request->setUserResolver(fn () => $user);
        $this->assertTrue($request->authorize());
    }

    // ── Helper ──

    private function createAuthenticatedUser(): User
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->attach($tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $tenant->id);

        return $user;
    }
}
