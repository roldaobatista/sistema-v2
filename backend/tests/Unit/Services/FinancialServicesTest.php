<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CashFlowProjectionService;
use App\Services\CreditRiskAnalysisService;
use App\Services\CustomerConversionService;
use App\Services\DebtRenegotiationService;
use App\Services\DREService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FinancialServicesTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── CashFlowProjectionService ──

    public function test_cash_flow_service_can_be_instantiated(): void
    {
        $service = app(CashFlowProjectionService::class);
        $this->assertInstanceOf(CashFlowProjectionService::class, $service);
    }

    // ── DREService ──

    public function test_dre_service_can_be_instantiated(): void
    {
        $service = app(DREService::class);
        $this->assertInstanceOf(DREService::class, $service);
    }

    // ── CreditRiskAnalysisService ──

    public function test_credit_risk_service_can_be_instantiated(): void
    {
        $service = app(CreditRiskAnalysisService::class);
        $this->assertInstanceOf(CreditRiskAnalysisService::class, $service);
    }

    // ── CustomerConversionService ──

    public function test_customer_conversion_service_can_be_instantiated(): void
    {
        $service = app(CustomerConversionService::class);
        $this->assertInstanceOf(CustomerConversionService::class, $service);
    }

    // ── DebtRenegotiationService ──

    public function test_debt_renegotiation_service_can_be_instantiated(): void
    {
        $service = app(DebtRenegotiationService::class);
        $this->assertInstanceOf(DebtRenegotiationService::class, $service);
    }
}
