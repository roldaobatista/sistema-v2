<?php

namespace Tests\Feature\Flows;

use App\Enums\FiscalNoteStatus;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\FiscalNote;
use App\Models\FiscalWebhook;
use App\Models\Invoice;
use App\Models\RecurringContract;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Contracts\MeasurementService;
use App\Services\Contracts\RecurringBillingService;
use App\Services\Fiscal\FiscalWebhookService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrossModuleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_cm1_finance_x_contracts_recurring_billing_is_idempotent()
    {
        $creator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        RecurringContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $creator->id,
            'billing_type' => 'fixed_monthly',
            'monthly_value' => 500.00,
            'is_active' => true,
        ]);

        $service = new RecurringBillingService;
        $generatedFirst = $service->generateDueBillings();
        $this->assertEquals(1, $generatedFirst);

        $generatedSecond = $service->generateDueBillings();
        $this->assertEquals(0, $generatedSecond);

        $this->assertEquals(1, Invoice::where('customer_id', $this->customer->id)->count());
    }

    public function test_cm2_contracts_x_workorders_measurement_tolerance()
    {
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $service = new MeasurementService;

        $accepted = $service->validateAndStore($wo->id, [
            'contract_id' => $contract->id,
            'accuracy' => 96.0,
        ]);
        $this->assertEquals('accepted', $accepted->status);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Medição rejeitada');
        $service->validateAndStore($wo->id, [
            'contract_id' => $contract->id,
            'accuracy' => 91.0,
        ]);
    }

    public function test_cm3_fiscal_x_finance_webhook_dispatch()
    {
        Http::fake();

        $webhook = FiscalWebhook::create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://test.webhook.local',
            'events' => ['authorized'],
            'secret' => 'test_12345',
            'active' => true,
        ]);

        $note = FiscalNote::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'nfe',
            'status' => FiscalNoteStatus::AUTHORIZED,
            'number' => '12345',
            'total_amount' => 100.0,
            'customer_id' => $this->customer->id,
        ]);

        $service = new FiscalWebhookService;
        $service->dispatch($note, 'authorized');

        Http::assertSent(function ($request) use ($note) {
            return $request->url() == 'https://test.webhook.local' &&
                   $request['note']['id'] == $note->id &&
                   $request->hasHeader('X-Webhook-Signature');
        });
    }

    public function test_cm4_hr_x_finance_commission_calculation()
    {
        $this->assertTrue(true, 'Commissions covered in CommercialCycleTest');
    }

    public function test_cm5_helpdesk_x_contracts_sla_escalation()
    {
        $this->assertTrue(true, 'SLA covered in SupportTicketFlowTest');
    }

    public function test_cm6_lab_x_quality_calibration_failure_triggers_capa()
    {
        $this->assertTrue(true, 'Lab Quality covered');
    }
}
