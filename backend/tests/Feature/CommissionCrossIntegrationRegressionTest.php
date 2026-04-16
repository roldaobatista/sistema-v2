<?php

namespace Tests\Feature;

use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\CommissionService;
use Tests\TestCase;

class CommissionCrossIntegrationRegressionTest extends TestCase
{
    private Tenant $tenant;

    private User $seller;

    private Customer $customer;

    private CommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        $this->service = app(CommissionService::class);
    }

    public function test_seller_source_filter_generates_commission_only_when_quote_source_matches(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'status' => Quote::STATUS_APPROVED,
            'source' => 'indicacao',
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_id' => $quote->id,
            'seller_id' => $this->seller->id,
            'created_by' => $this->seller->id,
            'total' => 2000.00,
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->seller->id,
            'name' => 'Regra vendedor sem match',
            'type' => 'percentage',
            'value' => 5,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_SELLER,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
            'source_filter' => 'retorno',
            'priority' => 20,
        ]);

        $matchedRule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->seller->id,
            'name' => 'Regra vendedor com match',
            'type' => 'percentage',
            'value' => 7,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_SELLER,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
            'source_filter' => 'indicacao',
            'priority' => 10,
        ]);

        $events = $this->service->calculateAndGenerate($workOrder, CommissionRule::WHEN_OS_COMPLETED);

        $this->assertCount(1, $events);
        $this->assertSame($this->seller->id, $events[0]->user_id);
        $this->assertSame($matchedRule->id, $events[0]->commission_rule_id);
        $this->assertStringContainsString('trigger:os_completed', (string) $events[0]->notes);
    }
}
