<?php

namespace Tests\Unit;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CashFlowProjectionService;
use App\Services\DREService;
use Tests\TestCase;

class FinancialProjectionServicesTest extends TestCase
{
    public function test_cash_flow_projection_service_uses_payment_date_and_open_balance(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'amount' => 1000.00,
            'amount_paid' => 300.00,
            'status' => 'partial',
            'due_date' => '2025-02-10',
            'paid_at' => null,
        ]);

        $payable = AccountPayable::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'amount' => 800.00,
            'amount_paid' => 0,
            'status' => 'pending',
            'due_date' => '2025-03-12',
            'paid_at' => null,
        ]);

        Payment::factory()->create([
            'tenant_id' => $tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $payable->id,
            'received_by' => $user->id,
            'amount' => 200.00,
            'payment_date' => '2025-03-07',
        ]);

        $result = app(CashFlowProjectionService::class)->project(
            now()->parse('2025-03-01'),
            now()->parse('2025-03-31'),
            $tenant->id
        );

        $this->assertEquals('0.00', $result['summary']['entradas_previstas']);
        $this->assertEquals('0.00', $result['summary']['entradas_realizadas']);
        $this->assertEquals('600.00', $result['summary']['saidas_previstas']);
        $this->assertEquals('200.00', $result['summary']['saidas_realizadas']);
    }

    public function test_cash_flow_projection_service_ignores_cancelled_and_renegotiated_payables(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        AccountPayable::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'amount' => 900.00,
            'amount_paid' => 100.00,
            'status' => 'renegotiated',
            'due_date' => '2025-03-12',
        ]);

        AccountPayable::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'amount' => 700.00,
            'amount_paid' => 0.00,
            'status' => 'cancelled',
            'due_date' => '2025-03-14',
        ]);

        $result = app(CashFlowProjectionService::class)->project(
            now()->parse('2025-03-01'),
            now()->parse('2025-03-31'),
            $tenant->id
        );

        $this->assertEquals('0.00', $result['summary']['saidas_previstas']);
        $this->assertEquals('100.00', $result['summary']['saidas_realizadas']);
        $this->assertEquals('100.00', $result['summary']['total_saidas']);
    }

    public function test_dre_service_uses_payment_date_and_legacy_fallback(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $receivableWithPayment = AccountReceivable::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'amount' => 900.00,
            'amount_paid' => 900.00,
            'status' => 'paid',
            'paid_at' => '2025-02-01',
            'due_date' => '2025-01-01',
        ]);

        Payment::factory()->create([
            'tenant_id' => $tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivableWithPayment->id,
            'received_by' => $user->id,
            'amount' => 900.00,
            'payment_date' => '2025-03-10',
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'amount' => 400.00,
            'amount_paid' => 400.00,
            'status' => 'paid',
            'paid_at' => '2025-03-12',
            'due_date' => '2025-02-01',
        ]);

        $payableWithPayment = AccountPayable::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'amount' => 250.00,
            'amount_paid' => 250.00,
            'status' => 'paid',
            'paid_at' => '2025-01-01',
            'due_date' => '2025-02-01',
        ]);

        Payment::factory()->create([
            'tenant_id' => $tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $payableWithPayment->id,
            'received_by' => $user->id,
            'amount' => 250.00,
            'payment_date' => '2025-03-15',
        ]);

        AccountPayable::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'amount' => 150.00,
            'amount_paid' => 150.00,
            'status' => 'paid',
            'paid_at' => '2025-03-18',
            'due_date' => '2025-02-10',
        ]);

        $result = app(DREService::class)->generate(
            now()->parse('2025-03-01'),
            now()->parse('2025-03-31'),
            $tenant->id
        );

        $this->assertEquals('1300.00', $result['receitas_brutas']);
        $this->assertEquals('400.00', $result['despesas_financeiras']);
    }
}
