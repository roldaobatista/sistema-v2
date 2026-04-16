<?php

use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\CrmSmartAlert;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\ChurnCalculationService;
use App\Services\Crm\CrmSmartAlertGenerator;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
});

// ── CRM Deal States ──

test('deal is created with open status by default', function () {
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);

    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    expect($deal->status)->toBe(CrmDeal::STATUS_OPEN);
});

test('deal can transition to won', function () {
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->won()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);

    $deal = CrmDeal::factory()->won()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    expect($deal->status)->toBe(CrmDeal::STATUS_WON);
    expect($deal->won_at)->not->toBeNull();
    expect($deal->probability)->toBe(100);
});

test('deal can transition to lost with reason', function () {
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->lost()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);

    $deal = CrmDeal::factory()->lost()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    expect($deal->status)->toBe(CrmDeal::STATUS_LOST);
    expect($deal->lost_at)->not->toBeNull();
    expect($deal->lost_reason)->not->toBeNull();
    expect($deal->probability)->toBe(0);
});

test('pipeline value is sum of open deals', function () {
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);

    CrmDeal::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
        'value' => 10000.00,
        'status' => CrmDeal::STATUS_OPEN,
    ]);

    $totalValue = CrmDeal::where('pipeline_id', $pipeline->id)
        ->where('status', CrmDeal::STATUS_OPEN)
        ->sum('value');

    expect((float) $totalValue)->toBe(30000.00);
});

test('win rate calculation across deals', function () {
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);

    CrmDeal::factory()->count(3)->won()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    CrmDeal::factory()->count(2)->lost()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    $total = CrmDeal::where('pipeline_id', $pipeline->id)
        ->whereIn('status', [CrmDeal::STATUS_WON, CrmDeal::STATUS_LOST])
        ->count();

    $won = CrmDeal::where('pipeline_id', $pipeline->id)
        ->where('status', CrmDeal::STATUS_WON)
        ->count();

    $winRate = ($won / $total) * 100;

    expect($winRate)->toBe(60.0);
});

test('weighted forecast uses probability and value', function () {
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);

    CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
        'value' => 10000.00,
        'probability' => 80,
        'status' => CrmDeal::STATUS_OPEN,
    ]);

    CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
        'value' => 5000.00,
        'probability' => 50,
        'status' => CrmDeal::STATUS_OPEN,
    ]);

    $forecast = CrmDeal::where('pipeline_id', $pipeline->id)
        ->where('status', CrmDeal::STATUS_OPEN)
        ->get()
        ->sum(fn ($d) => $d->value * $d->probability / 100);

    // 10000*0.8 + 5000*0.5 = 8000 + 2500 = 10500
    expect($forecast)->toBe(10500.0);
});

// ── ChurnCalculationService ──

test('churn score starts at 100 for customer with no negative factors', function () {
    // Create a recent completed WO
    DB::table('work_orders')->insert([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'number' => 'OS-TEST-001',
        'description' => 'Test Work Order',
        'status' => 'completed',
        'completed_at' => now()->subDays(30),
        'total' => 1000,
        'priority' => 'normal',
        'origin_type' => 'manual',
        'created_at' => now()->subDays(60),
        'updated_at' => now(),
    ]);

    $service = app(ChurnCalculationService::class);
    $result = $service->calculateScore($this->tenant->id, $this->customer->id);

    expect($result['health_index'])->toBeGreaterThanOrEqual(90);
    expect($result['risk_level'])->toBe('low');
});

test('churn score decreases for customer with overdue payments', function () {
    DB::table('accounts_receivable')->insert([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'description' => 'Test AR',
        'amount' => 1000,
        'amount_paid' => 0,
        'due_date' => now()->subDays(30),
        'status' => 'overdue',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(ChurnCalculationService::class);
    $result = $service->calculateScore($this->tenant->id, $this->customer->id);

    expect($result['health_index'])->toBeLessThan(100);
    expect($result['factors'])->not->toBeEmpty();
});

test('churn score clamped between 0 and 100', function () {
    $service = app(ChurnCalculationService::class);
    $result = $service->calculateScore($this->tenant->id, $this->customer->id);

    expect($result['health_index'])->toBeGreaterThanOrEqual(0);
    expect($result['health_index'])->toBeLessThanOrEqual(100);
});

// ── CrmSmartAlertGenerator ──

test('generates alert for stalled deal', function () {
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);

    CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
        'status' => CrmDeal::STATUS_OPEN,
        'updated_at' => now()->subDays(20),
    ]);

    $generator = app(CrmSmartAlertGenerator::class);
    $count = $generator->generateForTenant($this->tenant->id);

    expect($count)->toBeGreaterThanOrEqual(1);

    $alert = CrmSmartAlert::where('tenant_id', $this->tenant->id)
        ->where('type', 'deal_stalled')
        ->first();

    expect($alert)->not->toBeNull();
    expect($alert->priority)->toBe('medium');
});

test('does not duplicate alerts for same stalled deal', function () {
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);

    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
        'status' => CrmDeal::STATUS_OPEN,
        'updated_at' => now()->subDays(20),
    ]);

    $generator = app(CrmSmartAlertGenerator::class);
    $generator->generateForTenant($this->tenant->id);
    $generator->generateForTenant($this->tenant->id);

    $alertCount = CrmSmartAlert::where('tenant_id', $this->tenant->id)
        ->where('type', 'deal_stalled')
        ->where('deal_id', $deal->id)
        ->count();

    expect($alertCount)->toBe(1);
});
