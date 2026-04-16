<?php

use App\Enums\CommissionEventStatus;
use App\Models\CommissionCampaign;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\CommissionService;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = app(CommissionService::class);
});

// ── CommissionRule::calculateCommission (unit-level) ──

test('calculates percentage commission on gross correctly', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
        'value' => 10.00,
    ]);

    $result = $rule->calculateCommission(10000.00, ['gross' => '10000']);

    expect($result)->toBe('1000.00');
});

test('calculates percentage commission on net (gross - expenses - cost)', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_PERCENT_NET,
        'value' => 10.00,
    ]);

    $result = $rule->calculateCommission(10000.00, [
        'gross' => '10000',
        'expenses' => '2000',
        'cost' => '1000',
    ]);

    // net = 10000 - 2000 - 1000 = 7000; 10% of 7000 = 700
    expect($result)->toBe('700.00');
});

test('calculates percentage commission minus displacement', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS_MINUS_DISPLACEMENT,
        'value' => 5.00,
    ]);

    $result = $rule->calculateCommission(10000.00, [
        'displacement' => '500',
    ]);

    // (10000 - 500) * 0.05 = 475
    expect($result)->toBe('475.00');
});

test('calculates commission on services only', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_PERCENT_SERVICES_ONLY,
        'value' => 8.00,
    ]);

    $result = $rule->calculateCommission(10000.00, [
        'services_total' => '6000',
        'products_total' => '4000',
    ]);

    // 8% of 6000 = 480
    expect($result)->toBe('480.00');
});

test('calculates commission on products only', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_PERCENT_PRODUCTS_ONLY,
        'value' => 3.00,
    ]);

    $result = $rule->calculateCommission(10000.00, [
        'products_total' => '4000',
        'services_total' => '6000',
    ]);

    // 3% of 4000 = 120
    expect($result)->toBe('120.00');
});

test('calculates fixed commission per OS', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_FIXED_PER_OS,
        'value' => 150.00,
    ]);

    $result = $rule->calculateCommission(10000.00);

    expect($result)->toBe('150.00');
});

test('calculates fixed commission per item', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_FIXED_PER_ITEM,
        'value' => 25.00,
    ]);

    $result = $rule->calculateCommission(10000.00, ['items_count' => 4]);

    // 25 * 4 = 100
    expect($result)->toBe('100.00');
});

test('calculates profit-based commission', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_PERCENT_PROFIT,
        'value' => 15.00,
    ]);

    $result = $rule->calculateCommission(10000.00, ['cost' => '3000']);

    // profit = 10000 - 3000 = 7000; 15% of 7000 = 1050
    expect($result)->toBe('1050.00');
});

test('calculates commission minus expenses', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS_MINUS_EXPENSES,
        'value' => 10.00,
    ]);

    $result = $rule->calculateCommission(10000.00, ['expenses' => '1500']);

    // (10000 - 1500) * 0.10 = 850
    expect($result)->toBe('850.00');
});

test('calculates tiered progressive commission correctly', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_TIERED_GROSS,
        'value' => 0, // unused for tiered
        'tiers' => [
            ['up_to' => 5000, 'percent' => 5],
            ['up_to' => 10000, 'percent' => 8],
            ['up_to' => null, 'percent' => 12],
        ],
    ]);

    // 5000 * 5% = 250, 5000 * 8% = 400, 5000 * 12% = 600 => 1250 total for 15000
    $result = $rule->calculateCommission(15000.00);

    expect($result)->toBe('1250.00');
});

test('tiered commission with value within first tier', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_TIERED_GROSS,
        'value' => 0,
        'tiers' => [
            ['up_to' => 5000, 'percent' => 5],
            ['up_to' => 10000, 'percent' => 8],
        ],
    ]);

    $result = $rule->calculateCommission(3000.00);

    // 3000 * 5% = 150
    expect($result)->toBe('150.00');
});

test('returns zero commission for zero base value with percentage', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
        'value' => 10.00,
    ]);

    $result = $rule->calculateCommission(0);

    expect($result)->toBe('0.00');
});

test('fixed per OS returns value regardless of base amount', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_FIXED_PER_OS,
        'value' => 200.00,
    ]);

    $resultZero = $rule->calculateCommission(0);
    $resultLarge = $rule->calculateCommission(999999.00);

    expect($resultZero)->toBe('200.00');
    expect($resultLarge)->toBe('200.00');
});

test('percentage handles very large base amount', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
        'value' => 5.00,
    ]);

    $result = $rule->calculateCommission(1000000.00);

    expect($result)->toBe('50000.00');
});

test('percentage with fractional values rounds to 2 decimals', function () {
    $rule = CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
        'value' => 7.50,
    ]);

    $result = $rule->calculateCommission(333.33);

    // 333.33 * 0.075 = 24.99975 => truncated by bcmul to 24.99
    expect(floatval($result))->toBeLessThanOrEqual(25.00);
    expect(floatval($result))->toBeGreaterThan(24.00);
});

// ── CommissionService integration ──

test('does not generate commission for warranty work orders', function () {
    Event::fake();

    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->user->id,
        'total' => 5000,
        'is_warranty' => true,
    ]);

    $events = $this->service->calculateAndGenerate($wo);

    expect($events)->toBeEmpty();
});

test('does not generate commission for zero total work order', function () {
    Event::fake();

    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->user->id,
        'total' => 0,
    ]);

    $events = $this->service->calculateAndGenerate($wo);

    expect($events)->toBeEmpty();
});

test('generates commission event for technician with matching rule', function () {
    Event::fake();

    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->user->id,
        'total' => 10000,
    ]);

    CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => null,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
        'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
        'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        'value' => 10.00,
        'active' => true,
        'priority' => 1,
    ]);

    $events = $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);

    expect($events)->toHaveCount(1);
    expect((float) $events[0]->commission_amount)->toBe(1000.00);
    expect($events[0]->status)->toBe(CommissionEventStatus::PENDING);
    expect($events[0]->user_id)->toBe($this->user->id);
});

test('prevents duplicate commission generation for same OS and trigger', function () {
    Event::fake();

    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->user->id,
        'total' => 10000,
    ]);

    CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => null,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
        'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
        'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        'value' => 10.00,
        'active' => true,
        'priority' => 1,
    ]);

    $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);

    expect(fn () => $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED))
        ->toThrow(HttpException::class);
});

test('applies highest priority rule per beneficiary', function () {
    Event::fake();

    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->user->id,
        'total' => 10000,
    ]);

    CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => null,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
        'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
        'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        'value' => 5.00,
        'active' => true,
        'priority' => 0,
    ]);

    CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => null,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
        'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
        'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        'value' => 15.00,
        'active' => true,
        'priority' => 10, // higher priority
    ]);

    $events = $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);

    expect($events)->toHaveCount(1);
    // Priority 10 rule (15%) should be used => 10000 * 0.15 = 1500
    expect((float) $events[0]->commission_amount)->toBe(1500.00);
});

test('skips inactive rules', function () {
    Event::fake();

    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->user->id,
        'total' => 10000,
    ]);

    CommissionRule::factory()->inactive()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => null,
        'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
        'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        'value' => 10.00,
    ]);

    $events = $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);

    expect($events)->toBeEmpty();
});

test('simulation returns correct data without persisting', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->user->id,
        'total' => 8000,
    ]);

    CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => null,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
        'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
        'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        'value' => 10.00,
        'active' => true,
        'priority' => 1,
    ]);

    $simulations = $this->service->simulate($wo);

    expect($simulations)->toHaveCount(1);
    expect($simulations[0]['commission_amount'])->toBe('800.00');
    expect($simulations[0]['base_amount'])->toBe('8000.00');
    expect(CommissionEvent::where('work_order_id', $wo->id)->count())->toBe(0);
});

test('GAP-07 blocks seller earning both tech and seller commission on same OS', function () {
    Event::fake();

    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->user->id,
        'seller_id' => $this->user->id, // same person
        'total' => 10000,
    ]);

    CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => null,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
        'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
        'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        'value' => 10.00,
        'active' => true,
        'priority' => 1,
    ]);

    CommissionRule::factory()->forSeller()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => null,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
        'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        'value' => 5.00,
        'active' => true,
        'priority' => 1,
    ]);

    $events = $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);

    // Only technician commission should be generated, seller blocked by GAP-07
    expect($events)->toHaveCount(1);
    expect($events[0]->user_id)->toBe($this->user->id);
});

test('campaign multiplier is applied to commission amount', function () {
    Event::fake();

    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'assigned_to' => $this->user->id,
        'total' => 10000,
    ]);

    CommissionRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => null,
        'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
        'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
        'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        'value' => 10.00,
        'active' => true,
        'priority' => 1,
    ]);

    CommissionCampaign::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Double Commission Month',
        'multiplier' => 2.0,
        'applies_to_role' => null, // all roles
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'active' => true,
    ]);

    $events = $this->service->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);

    expect($events)->toHaveCount(1);
    // 10000 * 10% = 1000 * 2 (campaign) = 2000
    expect((float) $events[0]->commission_amount)->toBe(2000.00);
});
